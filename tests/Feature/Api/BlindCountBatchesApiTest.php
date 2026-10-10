<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Item;
use App\Models\StockLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Blind counting (master prompt §6): the system quantity ('remaining_qty') must be
 * fully ABSENT from the JSON for a Staff-only user, not merely null/zero — and present
 * for Manager/Owner. Asserts key absence, not just value, per the task's explicit
 * requirement.
 */
class BlindCountBatchesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_response_omits_system_quantity_key_but_manager_response_includes_it(): void
    {
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);

        $branch = Branch::create(['name' => 'Main Branch', 'status' => true]);

        $item = Item::create([
            'name' => 'Cat Food 500g',
            'item_code' => 'CF001',
            'cost_price' => 50,
            'sell_price' => 80,
            'mrp' => 90,
            'status' => true,
        ]);

        // Give the item some stock-ledger history so a batch row actually exists.
        StockLedger::create([
            'item_id' => $item->id,
            'branch_id' => $branch->id,
            'batch_no' => 'BATCH-001',
            'exp_date' => now()->addDays(45)->toDateString(),
            'movement_type' => 'OPENING',
            'qty_in' => 20,
            'qty_out' => 0,
            'unit_cost' => 50,
            'value_in' => 1000,
            'value_out' => 0,
            'running_balance_qty' => 20,
            'running_balance_value' => 1000,
            'document_date' => now()->toDateString(),
            'posted_at' => now(),
        ]);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole('Staff');

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole('Manager');

        // --- Staff: system quantity key must be entirely absent ---
        Sanctum::actingAs($staff, ['*']);
        $staffResponse = $this->getJson("/api/v1/items/{$item->id}/batches?branch_id={$branch->id}");
        $staffResponse->assertOk();

        $staffJson = $staffResponse->json('data');
        $this->assertNotEmpty($staffJson, 'Expected at least one batch row in the staff response.');
        foreach ($staffJson as $batchRow) {
            $this->assertArrayNotHasKey('remaining_qty', $batchRow, 'Blind counting violated: remaining_qty key present in Staff response.');
        }
        // Also assert the raw response body text never contains the key at all.
        $staffResponse->assertDontSee('remaining_qty', false);

        // --- Manager: system quantity key must be present ---
        Sanctum::actingAs($manager, ['*']);
        $managerResponse = $this->getJson("/api/v1/items/{$item->id}/batches?branch_id={$branch->id}");
        $managerResponse->assertOk();

        $managerJson = $managerResponse->json('data');
        $this->assertNotEmpty($managerJson, 'Expected at least one batch row in the manager response.');
        foreach ($managerJson as $batchRow) {
            $this->assertArrayHasKey('remaining_qty', $batchRow);
        }
    }
}
