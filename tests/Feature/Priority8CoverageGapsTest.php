<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesReturn;
use App\Models\User;
use App\Services\Tax\TaxEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Priority 8: targeted tests for the 15 methods the PCOV coverage run (2026-09-27, 98.51% lines) flagged as
 * not-fully-covered. Each test here closes a genuinely untested BUSINESS scenario (verified by reading the
 * method and its real call sites first) — the other flagged lines were confirmed to be either defensive
 * guards unreachable given upstream validation, or (for LedgerPostingService::nextNumber's 'Contra'/default
 * arms) dead code from this specific class's own call sites (Contra vouchers use VoucherController's own,
 * separate numbering method) — see docs/PRIORITY8-COVERAGE-GAPS.md for the full classification.
 */
class Priority8CoverageGapsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $owner;
    private GstTax $gst;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(); // burn hard-coded super-user id 1
        $this->branch = Branch::create(['name' => 'P8 Branch', 'state' => 'Gujarat']);
        $this->gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);
        $this->owner = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->owner->assignRole('Owner');
        $this->actingAs($this->owner);
    }

    private function item(string $code, array $extra = []): Item
    {
        return Item::create($extra + [
            'name' => "P8 $code", 'item_code' => $code, 'sell_price' => 100, 'mrp' => 120, 'cost_price' => 60,
            'gst_tax_id' => $this->gst->id, 'tax_inclusive' => false, 'status' => true,
        ]);
    }

    // ================================================================== TaxEngine

    public function test_tax_engine_derives_discount_amount_from_percent_when_only_percent_is_given(): void
    {
        // Was uncovered: the branch that computes disc_amount FROM disc_percent (every prior test passed a
        // pre-computed disc_amount directly). qty=2, price=100 => base=200; 10% => disc_amount=20; taxable=180;
        // GST 18% (not inclusive) => 32.4.
        $item = $this->item('TAXPCT');
        $result = app(TaxEngine::class)->calculate(qty: 2, price: 100, item: $item, discPercent: 10.0);

        $this->assertSame(20.0, $result['disc_amount']);
        $this->assertSame(180.0, $result['taxable_value']);
        $this->assertSame(32.4, $result['gst_tax_amount']);
        $this->assertSame(212.4, $result['net_amount']);
    }

    // ================================================================== SalesReturnController::validateData

    public function test_sales_return_rejects_item_the_customer_never_purchased(): void
    {
        // Was uncovered: the "customer hasn't purchased this item" anti-fraud check. Every existing return
        // test returns items from a bill that really was billed to that customer.
        $customer = Customer::create(['name' => 'P8 Cust', 'status' => true]);
        $boughtItem = $this->item('BOUGHT');
        $neverBoughtItem = $this->item('NEVERBOUGHT');
        $bill = SalesBill::create(['bill_number' => 'P8-SB-1', 'bill_date' => now()->toDateString(), 'customer_id' => $customer->id,
            'branch_id' => $this->branch->id, 'sales_type' => 'Local', 'total' => 100, 'status' => 'Posted']);
        SalesBillItem::create(['sales_bill_id' => $bill->id, 'item_id' => $boughtItem->id, 'qty' => 1, 'sell_price' => 100, 'net_amount' => 100]);

        $payload = [
            'return_date' => now()->toDateString(), 'customer_id' => $customer->id, 'branch_id' => $this->branch->id,
            'return_mode' => 'Cash', 'sales_type' => 'Local',
            'items' => [['item_id' => $neverBoughtItem->id, 'qty' => 1, 'sell_price' => 100, 'gst_percent' => 18]],
        ];

        $this->post(route('sales.sales-returns.store'), $payload)
            ->assertSessionHasErrors('items');
        $this->assertSame(0, SalesReturn::count());

        // Sanity: an item the customer DID buy is still accepted (the rule isn't overly broad).
        $payload['items'][0]['item_id'] = $boughtItem->id;
        $this->post(route('sales.sales-returns.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, SalesReturn::count());
    }

    // ================================================================== StockTransferController: malformed expiry data

    public function test_stock_transfer_item_by_code_tolerates_unparseable_expiry_date(): void
    {
        // Was uncovered: resolveItemExpiry()'s catch(\Throwable) when exp_date holds a garbage string
        // (legacy/bad-import data) instead of crashing the barcode-scan lookup with a 500.
        $item = $this->item('BADEXP', ['ean_upc_code' => '8900000099999']);
        ItemStock::create(['item_id' => $item->id, 'branch_id' => $this->branch->id, 'quantity' => 5, 'exp_date' => 'not-a-real-date']);

        $this->getJson(route('inventory.stock-transfers.item-by-code', ['code' => $item->ean_upc_code, 'branch_id' => $this->branch->id]))
            ->assertOk()
            ->assertJson(['found' => true])
            ->assertJsonPath('item.exp_date', null); // unparseable => treated as "no expiry", not a crash
    }

    public function test_stock_transfer_item_list_tolerates_unparseable_expiry_date_in_search(): void
    {
        // Was uncovered: the raw-SQL item-list search's own catch(\Throwable) fallback (keeps the raw string
        // instead of a formatted date) when a batch's exp_date can't be parsed.
        $item = $this->item('BADEXP2');
        ItemStock::create(['item_id' => $item->id, 'branch_id' => $this->branch->id, 'quantity' => 5, 'exp_date' => 'garbage-value']);

        $resp = $this->getJson(route('inventory.stock-transfers.item-list', ['search' => 'BADEXP2', 'from_branch_id' => $this->branch->id]))
            ->assertOk();
        $rows = $resp->json();
        $this->assertNotEmpty($rows, 'search must still return the item, not fail');
    }

    // ================================================================== EnsureBranchAccess: mixed route-parameter shapes

    public function test_ensure_branch_access_skips_non_object_and_branch_less_route_parameters(): void
    {
        // A Manager (not Owner) is scoped to their own branch. Direct middleware invocation with a route whose
        // parameters mix a non-object value and an object with no branch_id/from_branch_id/to_branch_id — both
        // previously-uncovered "keep scanning the other parameters" branches — followed by one real
        // branch-bearing parameter that must still be enforced.
        $manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $manager->assignRole('Manager');
        $otherBranch = Branch::create(['name' => 'P8 Other Branch', 'state' => 'Gujarat']);

        $customerNoBranchField = new class { public $id = 1; }; // object, but none of branch_id/from_branch_id/to_branch_id

        $route = new \Illuminate\Routing\Route('GET', 'p8-test/{a}/{b}/{c}', fn () => 'ok');
        $route->bind($request = \Illuminate\Http\Request::create('/p8-test/plain-string/x/y'));
        $route->setParameter('a', 'a-plain-string-route-param'); // non-object => must be skipped, not fatal
        $route->setParameter('b', $customerNoBranchField); // object with no branch fields => must be skipped
        $route->setParameter('c', (object) ['branch_id' => $otherBranch->id]); // real check: wrong branch => 403

        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $manager);

        $middleware = new \App\Http\Middleware\EnsureBranchAccess();
        try {
            $middleware->handle($request, fn ($req) => response('should not reach here'));
            $this->fail('expected a 403 HttpException');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_ensure_branch_access_allows_when_branch_bearing_parameter_matches(): void
    {
        $manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $manager->assignRole('Manager');
        $customerNoBranchField = new class { public $id = 1; };

        $route = new \Illuminate\Routing\Route('GET', 'p8-test/{a}/{b}/{c}', fn () => 'ok');
        $request = \Illuminate\Http\Request::create('/p8-test/plain-string/x/y');
        $route->bind($request);
        $route->setParameter('a', 'a-plain-string-route-param');
        $route->setParameter('b', $customerNoBranchField);
        $route->setParameter('c', (object) ['branch_id' => $this->branch->id]); // matches manager's own branch

        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $manager);

        $middleware = new \App\Http\Middleware\EnsureBranchAccess();
        $response = $middleware->handle($request, fn ($req) => response('passed through'));
        $this->assertSame('passed through', $response->getContent());
    }
}
