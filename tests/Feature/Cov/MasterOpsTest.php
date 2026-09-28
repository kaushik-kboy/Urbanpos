<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\CustomerLoyaltyPoint;
use App\Models\DocumentSequence;
use App\Models\LoyaltyProgram;
use App\Models\SalesBill;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Document numbering rules, backup download/delete safety and LoyaltyService branches
 * that the feature suites do not reach.
 */
class MasterOpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(); // burn id 1
        $u = User::factory()->create();
        $u->assignRole('Manager');
        $this->actingAs($u);
    }

    // ------------------------------------------------------------------ document sequences

    private function seqPayload(array $over = []): array
    {
        return array_merge([
            'document_type' => 'sales_bill', 'prefix' => ' INV/{FY}/ ', 'suffix' => '', 'padding_zeros' => 5,
            'starting_number' => 100, 'reset_frequency' => 'financial_year',
        ], $over);
    }

    public function test_document_sequence_create_then_update_same_rule_upserts(): void
    {
        $this->post(route('tools.document-sequences.update'), $this->seqPayload())->assertRedirect()->assertSessionHas('success');

        $seq = DocumentSequence::where('document_type', 'sales_bill')->whereNull('branch_id')->first();
        $this->assertSame('INV/{FY}/', $seq->prefix);
        $this->assertNull($seq->suffix);
        $this->assertSame(0, $seq->last_number);
        $this->assertSame('doc:sales_bill', $seq->series);
        $this->assertTrue($seq->is_active);

        $this->post(route('tools.document-sequences.update'), $this->seqPayload(['prefix' => 'B2', 'suffix' => '-X', 'padding_zeros' => 3, 'is_active' => 0]));
        $this->assertSame(1, DocumentSequence::where('document_type', 'sales_bill')->count());
        $seq->refresh();
        $this->assertSame('B2', $seq->prefix);
        $this->assertSame('-X', $seq->suffix);
        $this->assertFalse($seq->is_active);
    }

    public function test_document_sequence_branch_specific_rule_is_a_separate_row_with_branch_series(): void
    {
        $b = Branch::create(['name' => 'Seq Branch']);
        $this->post(route('tools.document-sequences.update'), $this->seqPayload());
        $this->postJson(route('tools.document-sequences.update'), $this->seqPayload(['branch_id' => $b->id, 'prefix' => 'BR']))
            ->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['preview', 'sequence']);

        $this->assertSame(2, DocumentSequence::where('document_type', 'sales_bill')->count());
        $this->assertSame("doc:sales_bill:{$b->id}", DocumentSequence::where('branch_id', $b->id)->value('series'));
    }

    public function test_document_sequence_validation(): void
    {
        foreach ([
            ['prefix' => ''], ['padding_zeros' => 0], ['padding_zeros' => 11], ['starting_number' => 0],
            ['reset_frequency' => 'hourly'], ['document_type' => ''],
        ] as $bad) {
            $this->post(route('tools.document-sequences.update'), $this->seqPayload($bad))->assertSessionHasErrors(array_keys($bad));
        }
        $this->assertSame(0, DocumentSequence::count());
    }

    public function test_document_sequence_reset_rewinds_counter_to_starting_number_minus_one(): void
    {
        $this->post(route('tools.document-sequences.update'), $this->seqPayload(['starting_number' => 100]));
        $seq = DocumentSequence::first();
        $seq->update(['last_number' => 250]);

        $this->postJson(route('tools.document-sequences.reset', $seq))->assertOk()->assertJsonPath('success', true);
        $this->assertSame(99, $seq->fresh()->last_number);

        $seq->update(['last_number' => 5, 'starting_number' => 1]);
        $this->post(route('tools.document-sequences.reset', $seq))->assertRedirect()->assertSessionHas('success');
        $this->assertSame(0, $seq->fresh()->last_number, 'never goes negative');
    }

    // ------------------------------------------------------------------ backup file safety (system health)

    public function test_backup_download_and_delete_reject_unknown_files(): void
    {
        $this->get(route('tools.system-health.backup.download', 'definitely-missing-file.sql.gz'))->assertStatus(404);
        $this->deleteJson(route('tools.system-health.backup.delete', 'definitely-missing-file.sql.gz'))
            ->assertStatus(404)->assertJson(['success' => false]);
    }

    public function test_backup_download_and_delete_work_for_an_existing_file_and_never_escape_the_directory(): void
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $name = 'covtest_'.uniqid().'.sql.gz';
        File::put($dir.'/'.$name, 'fake-backup-bytes');

        try {
            $res = $this->get(route('tools.system-health.backup.download', $name));
            $res->assertOk();
            $this->assertSame('application/gzip', $res->headers->get('content-type'));
            $this->assertStringContainsString($name, $res->headers->get('content-disposition'));

            // traversal attempt: encoded slash in the filename must not resolve to a file outside the dir
            $this->get('/tools/system-health/backup/download/'.rawurlencode('../../../composer.json'))->assertStatus(404);

            $this->deleteJson(route('tools.system-health.backup.delete', $name))->assertOk()->assertJson(['success' => true]);
            $this->assertFalse(File::exists($dir.'/'.$name));
        } finally {
            File::delete($dir.'/'.$name);
        }
    }

    // ------------------------------------------------------------------ LoyaltyService leftovers

    private function billFor(Customer $c, float $total = 500): SalesBill
    {
        $branch = Branch::create(['name' => 'LB'.uniqid()]);

        return SalesBill::create([
            'bill_number' => 'LB-'.uniqid(), 'bill_date' => now()->toDateString(), 'customer_id' => $c->id,
            'branch_id' => $branch->id, 'invoice_type' => 'Retail Invoice', 'delivery_type' => 'Delivered',
            'sales_type' => 'Local', 'payment_type' => 'Cash', 'total' => $total, 'status' => 'Posted',
        ]);
    }

    private function loyalCustomer(): Customer
    {
        $cat = CustomerCategory::create(['name' => 'LoyalCat', 'enable_loyalty' => true, 'status' => true]);

        return Customer::create(['name' => 'Loyal Lee', 'mobile' => '9000000066', 'customer_category_id' => $cat->id]);
    }

    public function test_customer_loyalty_summary_for_missing_customer_and_defaults_without_program(): void
    {
        $svc = new LoyaltyService();

        $missing = $svc->getCustomerLoyalty(999999);
        $this->assertFalse($missing['enable_loyalty']);
        $this->assertSame(0.0, $missing['balance_points']);
        $this->assertFalse($missing['can_redeem']);
        $this->assertSame(50, $missing['min_points_redeem']);

        $c = $this->loyalCustomer();
        LoyaltyProgram::query()->delete();
        $svc->manualAdjustment($c->id, 'Add', 60, 'welcome');

        $sum = $svc->getCustomerLoyalty($c->id);
        $this->assertTrue($sum['enable_loyalty']);
        $this->assertEquals(60, $sum['balance_points']);
        $this->assertEquals(60.0, $sum['rupee_value'], 'default 1 rupee per point without a program');
        $this->assertTrue($sum['can_redeem'], '60 >= default minimum of 50');
        $this->assertSame('Default Program', $sum['program_name']);
    }

    public function test_no_points_accrue_without_active_program_or_for_tiny_bills(): void
    {
        $svc = new LoyaltyService();
        $c = $this->loyalCustomer();

        LoyaltyProgram::query()->delete();
        $this->assertNull($svc->accruePointsForBill($this->billFor($c, 5000)));

        LoyaltyProgram::create(['name' => 'P', 'start_date' => now()->subDay()->toDateString(), 'based_on' => 'Bill Amount',
            'min_points_redeem' => 10, 'amount_per_point' => 1, 'points_per_hundred' => 1, 'roundoff' => true, 'status' => true]);
        $this->assertNull($svc->accruePointsForBill($this->billFor($c, 30)), 'Rs30 earns 0 points at 1 pt/Rs100 => no row');
        $this->assertSame(0, CustomerLoyaltyPoint::count());
    }

    public function test_redeem_ignores_non_positive_points_and_honours_explicit_amount_value(): void
    {
        $svc = new LoyaltyService();
        $c = $this->loyalCustomer();
        $bill = $this->billFor($c);

        $this->assertNull($svc->redeemPointsForBill($bill, 0));
        $this->assertNull($svc->redeemPointsForBill($bill, -5));

        $svc->manualAdjustment($c->id, 'Add', 100, 'seed');
        $r = $svc->redeemPointsForBill($bill, 40, 33.5);
        $this->assertSame('Redeemed', $r->type);
        $this->assertEquals(33.5, $r->amount_value);
        $this->assertEquals(60, $c->loyaltyBalance());
    }

    public function test_manual_deduct_uses_absolute_points_and_reversal_restores_both_directions(): void
    {
        $svc = new LoyaltyService();
        $c = $this->loyalCustomer();

        $d = $svc->manualAdjustment($c->id, 'Add', 100, 'seed');
        $svc->manualAdjustment($c->id, 'Deduct', -30, 'correction');
        $this->assertEquals(70, $c->loyaltyBalance());
        $this->assertSame('Adjustment_Deduct', CustomerLoyaltyPoint::where('remarks', 'correction')->value('type'));
        $this->assertNull(CustomerLoyaltyPoint::where('remarks', 'correction')->value('sales_bill_id'));

        $bill = $this->billFor($c, 1000);
        LoyaltyProgram::create(['name' => 'P', 'start_date' => now()->subDay()->toDateString(), 'based_on' => 'Bill Amount',
            'min_points_redeem' => 10, 'amount_per_point' => 1, 'points_per_hundred' => 1, 'roundoff' => true, 'status' => true]);
        $svc->accruePointsForBill($bill);               // +10
        $svc->redeemPointsForBill($bill, 20);            // -20
        $this->assertEquals(60, $c->loyaltyBalance());

        $svc->reverseBillPoints($bill);                  // earn reversed (-10), redemption refunded (+20)
        $this->assertEquals(70, $c->loyaltyBalance());
        $this->assertSame(2, CustomerLoyaltyPoint::where('sales_bill_id', $bill->id)->where('type', 'Reversal')->count());
    }
}
