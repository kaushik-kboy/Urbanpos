<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\SalesBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** BUG-P1: invoice search matched EVERY bill when any customer matched (uncorrelated OR) and cross-scanned customers. */
class InvoiceSearchRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function bill(string $no, Customer $c, Branch $b): SalesBill
    {
        return SalesBill::create(['bill_number' => $no, 'bill_date' => now(), 'customer_id' => $c->id, 'branch_id' => $b->id,
            'sales_type' => 'Local', 'total' => 100, 'status' => 'Posted']);
    }

    public function test_customer_name_search_returns_only_that_customers_bills(): void
    {
        User::factory()->create();
        $u = User::factory()->create();
        $u->assignRole('Owner');
        $b = Branch::create(['name' => 'B1', 'state' => 'Gujarat']);
        $shah = Customer::create(['name' => 'Shah Traders', 'mobile' => '9111111111', 'status' => true]);
        $patel = Customer::create(['name' => 'Patel Stores', 'mobile' => '9222222222', 'status' => true]);
        $this->bill('B-SHAH-1', $shah, $b);
        $this->bill('B-PATEL-1', $patel, $b);

        $r = $this->actingAs($u)->get(route('sales.sales-bills.index', ['search' => 'Shah', 'search_column' => 'customer_name', 'branch_id' => $b->id]));
        $bills = $r->viewData('salesBills')->pluck('bill_number')->all();
        $this->assertSame(['B-SHAH-1'], $bills);

        $r = $this->actingAs($u)->get(route('sales.sales-bills.index', ['search' => '9222', 'search_column' => 'mobile', 'branch_id' => $b->id]));
        $this->assertSame(['B-PATEL-1'], $r->viewData('salesBills')->pluck('bill_number')->all());
    }
}
