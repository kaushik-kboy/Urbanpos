<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Color;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The ~35 controllers that had zero permission checks (Master CRUD, Purchase Orders,
 * Finance Ledgers, Inventory "More Operations") all now route through the same
 * $gatedResource pattern proven in Phase 4, so this doesn't re-test every resource —
 * it tests the shared code path plus the one real branch point: `items` is split by
 * action (create = Manager+Owner, edit/cancel = Owner-only) because ItemController's
 * update() also writes price fields that item-price-change exists to gate.
 */
class PermissionGatingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function validItemPayload(): array
    {
        return [
            'name' => 'Gating Test Item',
            'product_type' => 'Standard',
            'cost_price' => 10,
            'landing_cost' => 10,
            'sell_price' => 15,
            'mrp' => 20,
            'status' => 1,
            'store_pickup' => 0,
            'tax_inclusive' => 0,
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => 0,
        ];
    }

    public function test_cashier_is_blocked_from_a_master_resource_and_from_editing_items(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('Cashier');
        $this->actingAs($cashier);

        $this->post(route('master.customers.store'), ['name' => 'Should Not Be Created'])
            ->assertForbidden();

        $item = Item::create(['name' => 'Cashier Edit Target']);
        $this->put(route('master.items.update', $item), $this->validItemPayload())
            ->assertForbidden();
    }

    public function test_manager_can_create_an_item_but_not_edit_an_existing_one(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Manager');
        $this->actingAs($manager);

        $created = $this->post(route('master.items.store'), $this->validItemPayload());
        $created->assertRedirect(route('master.items.index'));
        $this->assertDatabaseHas('items', ['name' => 'Gating Test Item']);

        $item = Item::where('name', 'Gating Test Item')->firstOrFail();
        $this->put(route('master.items.update', $item), $this->validItemPayload())
            ->assertForbidden();
    }

    public function test_manager_can_manage_non_price_master_data(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Manager');
        $this->actingAs($manager);

        $created = $this->post(route('master.colors.store'), ['name' => 'Gating Test Color', 'status' => 1]);
        $created->assertRedirect(route('master.colors.index'));

        $color = Color::where('name', 'Gating Test Color')->firstOrFail();
        $this->put(route('master.colors.update', $color), ['name' => 'Gating Test Color Renamed', 'status' => 1])
            ->assertRedirect(route('master.colors.index'));
    }

    public function test_owner_only_financial_and_structural_actions_are_blocked_for_manager(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Manager');
        $this->actingAs($manager);

        $this->post(route('master.branches.store'), ['name' => 'Should Not Be Created'])
            ->assertForbidden();

        $this->post(route('finance.ledgers.store'), [
            'name' => 'Should Not Be Created', 'ledger_group' => 'Cash in Hand',
            'opening_balance' => 0, 'opening_balance_type' => 'Debit', 'status' => 1,
        ])->assertForbidden();

        $this->post(route('master.gst-taxes.store'), ['description' => 'Should Not Be Created', 'percentage' => 5, 'status' => 1])
            ->assertForbidden();

        $this->post(route('inventory.price-fixing.apply'), [])->assertForbidden();
        $this->post(route('inventory.change-selling.update'), [])->assertForbidden();
    }

    public function test_owner_can_perform_the_owner_only_actions_manager_is_blocked_from(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');
        $this->actingAs($owner);

        // Full Branch validation needs many more fields than are relevant to a
        // permission test; the point here is only that Owner isn't blocked by the
        // gate Manager was blocked by above (a 422 from validation is fine, 403 is not).
        $this->assertNotEquals(403, $this->post(route('master.branches.store'), ['name' => 'Owner Created Branch'])->getStatusCode());

        $item = Item::create(['name' => 'Owner Edit Target']);
        $this->put(route('master.items.update', $item), $this->validItemPayload())
            ->assertRedirect(route('master.items.index'));
    }
}
