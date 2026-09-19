<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FormFieldValidation;
use App\Models\Item;
use App\Models\StockLedger;
use App\Models\StockUpdate;
use App\Models\User;
use Database\Seeders\FormFieldValidationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockUpdateApprovalAndFormValidationsLevelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(
            ['name' => 'Main Outlet'],
            ['code' => 'MAIN', 'state' => 'Maharashtra']
        );

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');

        $this->item = Item::firstOrCreate(
            ['name' => 'Synthetic Engine Oil 5W30'],
            [
                'item_code' => 'OIL5W30',
                'ean_upc_code' => '8901234567890',
                'sell_price' => 1500.00,
                'mrp' => 1600.00,
                'cost_price' => 1200.00,
                'status' => true,
            ]
        );

        $seeder = new FormFieldValidationSeeder();
        $seeder->run();
    }

    public function test_stock_update_is_created_with_pending_status_and_not_auto_approved(): void
    {
        $payload = [
            'branch_id' => $this->branch->id,
            'entry_date' => now()->toDateString(),
            'remarks' => 'Monthly physical audit count',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'item_code' => 'OIL5W30',
                    'physical_qty' => 25.000,
                    'exp_date' => now()->addMonths(12)->toDateString(),
                    'sell_price' => 1500.00,
                    'mrp' => 1600.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('inventory.stock-updates.store'), $payload);

        $response->assertRedirect(route('inventory.stock-updates.index'));

        $stockUpdate = StockUpdate::latest()->first();
        $this->assertNotNull($stockUpdate);
        $this->assertSame('Pending', $stockUpdate->status, 'Stock update must be Pending on creation, NOT Approved.');
        $this->assertTrue($stockUpdate->isPending());
        $this->assertFalse($stockUpdate->isPosted());

        // Assert no stock ledger movements posted yet while pending
        $ledgerCount = StockLedger::where('reference_type', StockUpdate::class)
            ->where('reference_id', $stockUpdate->id)
            ->count();
        $this->assertSame(0, $ledgerCount, 'Pending stock updates must NOT post to stock ledger before supervisor approval.');

        // Assert pending approval dashboard displays this entry with approval options
        $approvalPage = $this->actingAs($this->user)
            ->get(route('inventory.stock-update-approval.index'));

        $approvalPage->assertOk();
        $approvalPage->assertSee($stockUpdate->update_number);
        $approvalPage->assertSee('Approve');
        $approvalPage->assertSee('Reject');
    }

    public function test_supervisor_can_approve_stock_update_and_post_lines(): void
    {
        $stockUpdate = StockUpdate::create([
            'update_number' => 'STKU00001',
            'branch_id' => $this->branch->id,
            'entry_date' => now()->toDateString(),
            'status' => 'Pending',
        ]);

        $stockUpdate->items()->create([
            'item_id' => $this->item->id,
            'exp_date' => now()->addMonths(6)->toDateString(),
            'physical_qty' => 30.000,
            'system_qty_at_entry' => 20.000,
            'delta_qty' => 10.000,
            'sell_price' => 1500.00,
            'mrp' => 1600.00,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('inventory.stock-update-approval.approve', $stockUpdate));

        $response->assertRedirect(route('inventory.stock-update-approval.index'));
        $stockUpdate->refresh();

        $this->assertSame('Approved', $stockUpdate->status);
        $this->assertTrue($stockUpdate->isPosted());

        // Stock ledger movements must be posted now
        $ledgerEntry = StockLedger::where('reference_type', StockUpdate::class)
            ->where('reference_id', $stockUpdate->id)
            ->first();

        $this->assertNotNull($ledgerEntry);
        $this->assertEquals(10.000, (float) $ledgerEntry->qty_in);
        $this->assertSame('EXCESS', $ledgerEntry->movement_type);
    }

    public function test_supervisor_can_reject_stock_update(): void
    {
        $stockUpdate = StockUpdate::create([
            'update_number' => 'STKU00002',
            'branch_id' => $this->branch->id,
            'entry_date' => now()->toDateString(),
            'status' => 'Pending',
        ]);

        $stockUpdate->items()->create([
            'item_id' => $this->item->id,
            'physical_qty' => 15.000,
            'system_qty_at_entry' => 20.000,
            'delta_qty' => -5.000,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('inventory.stock-update-approval.reject', $stockUpdate));

        $response->assertRedirect(route('inventory.stock-update-approval.index'));
        $stockUpdate->refresh();

        $this->assertSame('Rejected', $stockUpdate->status);
        $this->assertTrue($stockUpdate->isRejected());

        // No movements posted
        $this->assertSame(0, StockLedger::where('reference_type', StockUpdate::class)->where('reference_id', $stockUpdate->id)->count());
    }

    public function test_stock_update_create_preserves_old_items_on_validation_failure(): void
    {
        // Missing branch_id causes validation error
        $payload = [
            'branch_id' => '', // missing
            'entry_date' => now()->toDateString(),
            'remarks' => 'Test retention',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'item_code' => 'OIL5W30',
                    'item_name' => 'Synthetic Engine Oil 5W30',
                    'physical_qty' => '14.500',
                    'exp_date' => '2027-05-20',
                    'sell_price' => '1500.00',
                    'mrp' => '1600.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->from(route('inventory.stock-updates.create'))
            ->post(route('inventory.stock-updates.store'), $payload);

        $response->assertRedirect(route('inventory.stock-updates.create'));
        $response->assertSessionHasErrors('branch_id');

        // Follow redirect to create page
        $createPage = $this->actingAs($this->user)
            ->get(route('inventory.stock-updates.create'));

        $createPage->assertOk();
        $createPage->assertSee('OIL5W30');
        $createPage->assertSee('Synthetic Engine Oil 5W30');
        $createPage->assertSee('14.500');
        $createPage->assertSee('2027-05-20');
        $createPage->assertSee('1500.00');
        $createPage->assertSee('1600.00');
    }

    public function test_form_field_validations_are_grouped_by_section_for_items(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('tools.form-validations.index', ['group' => 'master', 'module' => 'items']));

        $response->assertOk();

        // Check tabs / section cards exist for the 5 actual item tabs
        $response->assertSee('General');
        $response->assertSee('Taxes');
        $response->assertSee('Sales');
        $response->assertSee('Category');
        $response->assertSee('GST');

        // Check fields are present
        $response->assertSee('tax_inclusive');
        $response->assertSee('batch_expiry_details');
        $response->assertSee('department_value_id');
        $response->assertSee('gst_tax_id');
        $response->assertSee('hsn_code');
        $response->assertSee('item_code');
        $response->assertSee('mrp');

        // Check database sections
        $taxField = FormFieldValidation::where('module_key', 'items')->where('field_name', 'tax_inclusive')->first();
        $this->assertSame('Taxes', $taxField->section);

        $salesField = FormFieldValidation::where('module_key', 'items')->where('field_name', 'batch_expiry_details')->first();
        $this->assertSame('Sales', $salesField->section);

        $gstField = FormFieldValidation::where('module_key', 'items')->where('field_name', 'gst_tax_id')->first();
        $this->assertSame('GST', $gstField->section);

        $catField = FormFieldValidation::where('module_key', 'items')->where('field_name', 'department_value_id')->first();
        $this->assertSame('Category', $catField->section);

        $genField = FormFieldValidation::where('module_key', 'items')->where('field_name', 'name')->first();
        $this->assertSame('General', $genField->section);
    }

    public function test_form_field_validations_can_be_updated_and_active_section_preserved(): void
    {
        $hsnField = FormFieldValidation::where('module_key', 'items')->where('field_name', 'hsn_code')->first();

        $payload = [
            'module_key' => 'items',
            'group' => 'master',
            'active_section' => 'gst',
            'fields' => [
                $hsnField->id => [
                    'is_required' => '1',
                    'is_readonly' => '0',
                    'block_future_date' => '0',
                    'is_unique' => '0',
                    'custom_error_message' => 'HSN Code is mandatory.',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('tools.form-validations.update'), $payload);

        $response->assertRedirect(route('tools.form-validations.index', [
            'module' => 'items',
            'group' => 'master',
            'section' => 'gst',
        ]));

        $hsnField->refresh();
        $this->assertTrue($hsnField->is_required);
        $this->assertSame('HSN Code is mandatory.', $hsnField->custom_error_message);
    }
}
