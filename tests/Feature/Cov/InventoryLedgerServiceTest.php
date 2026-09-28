<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockLedger;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockLedgerService $svc;
    private Branch $branch;
    private int $uid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uid = User::factory()->create()->id;
        $this->svc = app(StockLedgerService::class);
        $this->branch = Branch::create(['name' => 'Ledger Br', 'state' => 'Gujarat']);
    }

    private function mv(Item $i, float $q, ?float $c = null, string $t = 'OPENING', ?string $ref = null, ?int $rid = null, ?string $exp = null)
    {
        return $this->svc->post($i->id, $this->branch->id, $t, $q, $c, $ref, $rid, '2026-09-01', $this->uid, null, null, $exp);
    }

    private function stock(Item $i): ItemStock
    {
        return ItemStock::where('item_id', $i->id)->where('branch_id', $this->branch->id)->first();
    }

    public function test_zero_delta_is_rejected(): void
    {
        $i = Item::create(['name' => 'Z']);
        $this->expectException(\InvalidArgumentException::class);
        $this->mv($i, 0.0, 10);
    }

    public function test_incoming_recomputes_weighted_average_and_outgoing_uses_it(): void
    {
        $i = Item::create(['name' => 'WAC']);
        $this->mv($i, 10, 100);
        $row = $this->mv($i, 10, 200);
        $this->assertEquals(150, (float) $this->stock($i)->cost_price);
        $this->assertEquals(20, (float) $row->running_balance_qty);
        $this->assertEquals(3000, (float) $row->running_balance_value);

        $out = $this->mv($i, -5, 999, 'SALE'); // supplied cost must be ignored on outgoing
        $this->assertEquals(150, (float) $out->unit_cost);
        $this->assertEquals(750, (float) $out->value_out);
        $this->assertEquals(5, (float) $out->qty_out);
        $this->assertEquals(0, (float) $out->qty_in);
        $this->assertEquals(15, (float) $this->stock($i)->quantity);
        $this->assertEquals(150, (float) $this->stock($i)->cost_price, 'Outgoing never changes the average.');
    }

    public function test_null_cost_incoming_keeps_existing_average(): void
    {
        $i = Item::create(['name' => 'NullCost']);
        $this->mv($i, 4, 80);
        $this->mv($i, 6, null, 'EXCESS');
        $this->assertEquals(80, (float) $this->stock($i)->cost_price);
        $this->assertEquals(10, (float) $this->stock($i)->quantity);
    }

    public function test_negative_stock_blocked_and_nothing_written(): void
    {
        $i = Item::create(['name' => 'NoNeg']);
        $this->mv($i, 3, 10);
        try {
            $this->mv($i, -4, null, 'SALE');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock', $e->errors());
            $this->assertStringContainsString('NoNeg', $e->errors()['stock'][0]);
        }
        $this->assertEquals(3, (float) $this->stock($i)->quantity);
        $this->assertEquals(1, StockLedger::where('item_id', $i->id)->count());
    }

    public function test_item_allowing_negative_stock_goes_below_zero(): void
    {
        $i = Item::create(['name' => 'AllowNeg', 'allow_negative_stock' => true]);
        $this->mv($i, 2, 10);
        $row = $this->mv($i, -5, null, 'SALE');
        $this->assertEquals(-3, (float) $this->stock($i)->quantity);
        $this->assertEquals(-3, (float) $row->running_balance_qty);
    }

    public function test_outgoing_of_exact_available_qty_is_allowed(): void
    {
        $i = Item::create(['name' => 'Exact']);
        $this->mv($i, 2.5, 10);
        $this->mv($i, -2.5, null, 'SALE');
        $this->assertEquals(0, (float) $this->stock($i)->quantity);
    }

    public function test_incoming_when_stock_is_negative_and_total_still_not_positive_uses_incoming_cost(): void
    {
        $i = Item::create(['name' => 'NegRecover', 'allow_negative_stock' => true]);
        $this->mv($i, -2, null, 'SALE');
        $this->mv($i, 2, 40, 'PURCHASE_RECEIPT'); // newQty == 0 -> avg = incoming cost
        $this->assertEquals(40, (float) $this->stock($i)->cost_price);
        $this->assertEquals(0, (float) $this->stock($i)->quantity);
    }

    public function test_expiry_date_is_persisted_on_ledger_row(): void
    {
        $i = Item::create(['name' => 'Exp']);
        $row = $this->mv($i, 5, 10, 'OPENING', null, null, '2027-01-31');
        $this->assertEquals('2027-01-31', $row->fresh()->exp_date->toDateString());
    }

    public function test_reverse_by_reference_restores_stock_exactly_and_is_idempotent(): void
    {
        $i = Item::create(['name' => 'Rev']);
        $this->mv($i, 10, 50);
        $this->mv($i, -4, null, 'DAMAGE', 'DocX', 77);
        $this->mv($i, 6, 70, 'PURCHASE_RECEIPT', 'DocX', 77, '2027-05-05');
        $this->assertEquals(12, (float) $this->stock($i)->quantity);

        $this->svc->reverseByReference('DocX', 77);

        $this->assertEquals(10, (float) $this->stock($i)->quantity);
        $reversals = StockLedger::where('reference_id', 77)->whereNotNull('reversal_of')->get();
        $this->assertCount(2, $reversals);
        $this->assertTrue($reversals->every(fn ($r) => $r->reason_code === 'REVERSAL'));
        $this->assertEquals('2027-05-05', $reversals->firstWhere('qty_out', '>', 0)->exp_date->toDateString());

        $this->svc->reverseByReference('DocX', 77); // second call must be a no-op
        $this->assertEquals(10, (float) $this->stock($i)->quantity);
        $this->assertEquals(2, StockLedger::where('reference_id', 77)->whereNotNull('reversal_of')->count());
    }

    public function test_reversing_an_incoming_movement_that_was_already_consumed_is_blocked(): void
    {
        $i = Item::create(['name' => 'Consumed']);
        $this->mv($i, 5, 10, 'PURCHASE_RECEIPT', 'DocY', 5);
        $this->mv($i, -5, null, 'SALE');
        try {
            $this->svc->reverseByReference('DocY', 5);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock', $e->errors());
        }
        $this->assertEquals(0, StockLedger::where('reference_id', 5)->whereNotNull('reversal_of')->count());
    }
}
