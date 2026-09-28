<?php

namespace Tests\Feature\Cov2;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait FinHelper
{
    use RefreshDatabase;

    protected User $owner;
    protected Branch $branch;
    protected Customer $cust;
    protected Supplier $supp;
    protected Item $item;
    protected GstTax $gst;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
        User::factory()->create(); // burn hard-coded super-user id 1

        $this->branch = Branch::create(['name' => 'Fin Branch', 'code' => 'FIN', 'state' => 'Gujarat']);
        $this->gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);
        $this->cust = Customer::create(['name' => 'Fin Customer', 'mobile' => '9777000001', 'state' => 'Gujarat', 'status' => true]);
        $this->supp = Supplier::create(['name' => 'Fin Supplier', 'phone' => '9777000002', 'state' => 'Gujarat']);
        $this->item = Item::create([
            'name' => 'Fin Item', 'item_code' => 'FIN-1', 'ean_upc_code' => '8901000000011', 'sell_price' => 100, 'mrp' => 100, 'cost_price' => 60,
            'gst_tax_id' => $this->gst->id, 'tax_inclusive' => true, 'status' => true, 'supplier_id' => $this->supp->id,
        ]);
        $this->owner = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->owner->assignRole('Owner');
        $this->actingAs($this->owner);
    }

    protected function stockOf(Item $item, ?Branch $branch = null): float
    {
        return (float) (ItemStock::where('item_id', $item->id)->where('branch_id', ($branch ?? $this->branch)->id)->value('quantity') ?? 0);
    }

    protected function seedStock(Item $item, float $qty, ?Branch $branch = null): void
    {
        ItemStock::updateOrCreate(['item_id' => $item->id, 'branch_id' => ($branch ?? $this->branch)->id], ['quantity' => $qty]);
    }
}
