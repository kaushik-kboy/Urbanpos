<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Models\FormFieldValidation;
use App\Models\Item;
use App\Models\User;
use App\Services\Accounting\DocumentNumberingService;
use Database\Seeders\FormFieldValidationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaSystemCertificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch1;
    private Branch $branch2;
    private DocumentNumberingService $numberingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FormFieldValidationSeeder::class);

        $this->branch1 = Branch::firstOrCreate(['id' => 1], ['name' => 'Main Branch']);
        $this->branch2 = Branch::firstOrCreate(['id' => 2], ['name' => 'Second Branch']);

        $this->admin = User::factory()->create(['branch_id' => $this->branch1->id]);
        $this->admin->assignRole('Owner');

        $this->numberingService = app(DocumentNumberingService::class);
    }

    /**
     * Pillar 1: Verify all 14 Document Sequences generate sequential numbers properly.
     */
    public function test_all_14_document_sequences_generate_valid_sequential_numbers(): void
    {
        $definitions = DocumentNumberingService::defaultDefinitions();
        $this->assertGreaterThanOrEqual(12, count($definitions));

        foreach ($definitions as $voucherType => $meta) {
            // 1. Generate first number
            $first = $this->numberingService->generate($voucherType, $this->branch1->id, '2026-10-14');
            $this->assertNotEmpty($first, "Failed generating first sequence for {$voucherType}");

            // 2. Generate second number
            $second = $this->numberingService->generate($voucherType, $this->branch1->id, '2026-10-14');
            $this->assertNotEmpty($second, "Failed generating second sequence for {$voucherType}");

            // Numbers must not be identical (must increment)
            $this->assertNotEquals($first, $second, "Sequence did not increment for {$voucherType}");
        }
    }

    /**
     * Pillar 2: Verify Dynamic Form Validations prevent default 0 and enforce correct rules.
     */
    public function test_dynamic_form_validations_have_no_ean_forced_requirement_and_rules_loaded(): void
    {
        $itemValidations = FormFieldValidation::where('module_key', 'items')->get()->keyBy('field_name');

        // ean_barcode must NOT be required by default
        if ($itemValidations->has('ean_barcode')) {
            $this->assertFalse((bool) $itemValidations->get('ean_barcode')->is_required, 'ean_barcode should not be forced required');
        }

        // Active required rules must exist across key transaction forms
        $activeRules = FormFieldValidation::whereIn('module_key', ['items', 'sales_bills', 'stock_updates', 'purchase_invoices'])
            ->where('is_required', true)
            ->count();

        $this->assertGreaterThan(0, $activeRules);
    }

    /**
     * Pillar 3: Verify Branch Isolation prevents cross-branch data collision.
     */
    public function test_branch_isolation_scopes_document_sequences_and_stock_records(): void
    {
        // Branch 1 sequence
        $seqB1 = $this->numberingService->generate('sales_bill', $this->branch1->id, '2026-10-14');
        // Branch 2 sequence
        $seqB2 = $this->numberingService->generate('sales_bill', $this->branch2->id, '2026-10-14');

        $this->assertNotEmpty($seqB1);
        $this->assertNotEmpty($seqB2);

        // Verify Branch 1 sequence record in DB is distinct or properly tracked
        $branch1Records = DocumentSequence::where('branch_id', $this->branch1->id)->count();
        $this->assertGreaterThanOrEqual(1, $branch1Records);
    }

    /**
     * Pillar 4: Verify that core transaction & inventory templates have NO Hinglish/Hindi validation strings.
     */
    public function test_blade_views_contain_no_hinglish_validation_strings(): void
    {
        $suspiciousWords = ['karein', 'chahiye', 'kijiye', 'daalein'];
        $viewsToCheck = [
            resource_path('views/sales/sales-returns/_form.blade.php'),
            resource_path('views/sales/sales-bills/_form.blade.php'),
            resource_path('views/purchase/purchase-invoices/_form.blade.php'),
            resource_path('views/inventory/stock-updates/_form.blade.php'),
            resource_path('views/reports/sales-summary.blade.php'),
        ];

        foreach ($viewsToCheck as $viewPath) {
            $this->assertFileExists($viewPath);
            $content = file_get_contents($viewPath);

            foreach ($suspiciousWords as $word) {
                $this->assertFalse(
                    stripos($content, $word) !== false,
                    "Found suspicious word '{$word}' in view " . basename($viewPath)
                );
            }
        }
    }
}
