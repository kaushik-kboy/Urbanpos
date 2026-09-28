<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuxEanTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_ean_upc_entry_page_renders_with_items_and_stats()
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->get(route('master.aux', 'item-ean-upc-entry'));

        $response->assertStatus(200);
        $response->assertViewIs('master.item-ean-upc');
        $response->assertViewHas(['items', 'totalCount', 'withEanCount', 'missingEanCount']);
        $response->assertSee('Item EAN / UPC Entry');
        $response->assertSee('Item Barcode Directory');
    }

    public function test_update_item_ean_upc_updates_barcode()
    {
        $user = User::first() ?? User::factory()->create();
        $item = Item::first() ?? Item::create(['name' => 'EAN Test Item', 'product_type' => 'Standard', 'cost_price' => 10, 'landing_cost' => 10, 'sell_price' => 15, 'mrp' => 20, 'status' => 1, 'batch_expiry_details' => 'Not Required']);

        $newBarcode = '8909999888877';
        $response = $this->actingAs($user)->postJson(route('master.aux.item-ean-upc.update'), [
            'item_id' => $item->id,
            'ean_upc_code' => $newBarcode,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals($newBarcode, $item->fresh()->ean_upc_code);
    }
}
