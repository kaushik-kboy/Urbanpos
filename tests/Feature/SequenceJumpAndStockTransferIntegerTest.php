<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequenceJumpAndStockTransferIntegerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch1;
    private Branch $branch2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch1 = Branch::create(['name' => 'Motera', 'code' => 'MOT', 'status' => true]);
        $this->branch2 = Branch::create(['name' => 'Satellite', 'code' => 'SAT', 'status' => true]);

        $this->admin = User::factory()->create([
            'email' => 'admin@urbanpos.com',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
    }

    public function test_pos_terminal_and_create_do_not_consume_sequence_number()
    {
        $seq = DocumentSequence::create([
            'document_type' => 'sales_bill',
            'prefix' => 'SI-{YEAR}-',
            'padding_zeros' => 4,
            'starting_number' => 1,
            'last_number' => 10,
            'reset_frequency' => 'never',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin);

        // Open POS terminal multiple times
        $res1 = $this->get(route('pos.terminal', ['branch_id' => $this->branch1->id]));
        $res1->assertStatus(200);

        $res2 = $this->get(route('pos.terminal', ['branch_id' => $this->branch1->id]));
        $res2->assertStatus(200);

        // Open create page
        $res3 = $this->get(route('sales.sales-bills.create', ['branch_id' => $this->branch1->id]));
        $res3->assertStatus(200);

        // Sequence last_number MUST still be 10!
        $this->assertEquals(10, $seq->fresh()->last_number);
    }

    public function test_document_sequence_update_allows_editing_last_number()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('tools.document-sequences.update'), [
            'document_type' => 'sales_bill',
            'prefix' => 'SI01/2627/',
            'padding_zeros' => 6,
            'starting_number' => 1,
            'last_number' => 1344,
            'reset_frequency' => 'never',
            'branch_id' => $this->branch2->id,
            'is_active' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $seq = DocumentSequence::where('document_type', 'sales_bill')->where('branch_id', $this->branch2->id)->first();
        $this->assertNotNull($seq);
        $this->assertEquals(1344, $seq->last_number);
    }

    public function test_cross_branch_stock_isolation()
    {
        $item = Item::create([
            'name' => 'Royal Canin',
            'item_code' => 'RC001',
            'cost_price' => 500,
            'sell_price' => 800,
            'status' => true,
        ]);

        // Branch 1 has 9 qty
        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $this->branch1->id,
            'quantity' => 9,
        ]);

        // Branch 2 has 6 qty
        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $this->branch2->id,
            'quantity' => 6,
        ]);

        $this->actingAs($this->admin);

        // Lookup in Branch 1 must return 9, NOT 15!
        $response1 = $this->getJson(route('sales.sales-bills.lookup-item', [
            'item_id' => $item->id,
            'branch_id' => $this->branch1->id,
        ]));
        $response1->assertStatus(200);
        $this->assertEquals(9, $response1->json('item.stock'));

        // Lookup in Branch 2 must return 6, NOT 15!
        $response2 = $this->getJson(route('sales.sales-bills.lookup-item', [
            'item_id' => $item->id,
            'branch_id' => $this->branch2->id,
        ]));
        $response2->assertStatus(200);
        $this->assertEquals(6, $response2->json('item.stock'));
    }

    public function test_stock_transfer_rejects_decimal_quantity()
    {
        $item = Item::create([
            'name' => 'Pedigree',
            'item_code' => 'PD001',
            'cost_price' => 100,
            'sell_price' => 150,
            'status' => true,
        ]);
        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $this->branch1->id,
            'quantity' => 50,
        ]);

        $this->actingAs($this->admin);

        // Attempt transfer with decimal qty 2.5 -> MUST fail validation
        $response = $this->post(route('inventory.stock-transfers.store'), [
            'transfer_date' => now()->format('Y-m-d'),
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2.5,
                    'unit_cost' => 100,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.qty']);
    }
}
