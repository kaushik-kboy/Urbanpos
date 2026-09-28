<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

trait PurchaseCovHelper
{
    use RefreshDatabase;

    protected User $owner;
    protected Branch $branch;
    protected Supplier $supplier;
    protected Item $item;
    protected Item $item2;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(); // burn hard-coded super-user id 1

        $this->branch = Branch::create(['name' => 'Cov Branch', 'code' => 'COV', 'state' => 'Gujarat']);
        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);
        $this->supplier = Supplier::create(['name' => 'Cov Supplier', 'phone' => '9000000001', 'state' => 'Gujarat']);
        $this->item = Item::create(['name' => 'Cov Item A', 'item_code' => 'COVA', 'ean_upc_code' => '8900000000011', 'cost_price' => 100, 'sell_price' => 150, 'mrp' => 160, 'gst_tax_id' => $gst->id, 'supplier_id' => $this->supplier->id]);
        $this->item2 = Item::create(['name' => 'Cov Item B', 'item_code' => 'COVB', 'cost_price' => 50, 'sell_price' => 80, 'mrp' => 90, 'gst_tax_id' => $gst->id]);

        $this->owner = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->owner->assignRole('Owner');
        $this->actingAs($this->owner);
    }

    protected function stockOf(Item $item): float
    {
        return (float) (ItemStock::where('item_id', $item->id)->where('branch_id', $this->branch->id)->value('quantity') ?? 0);
    }

    protected function invPayload(array $over = [], array $lineOver = []): array
    {
        return array_merge([
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'items' => [array_merge([
                'item_id' => $this->item->id, 'qty' => 10, 'free_qty' => 0, 'cost_price' => 100,
                'sell_price' => 150, 'mrp' => 160,
            ], $lineOver)],
        ], $over);
    }
}
