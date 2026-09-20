<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\User;
use App\Services\Accounting\DocumentNumberingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSequenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Motera Branch',
            'code' => 'MOT',
            'address' => 'Motera Stadium Road',
            'phone' => '7383056626',
            'status' => 1,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_guest_cannot_access_document_sequences(): void
    {
        $response = $this->get(route('tools.document-sequences.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_user_can_access_document_sequences_ui(): void
    {
        $response = $this->actingAs($this->user)->get(route('tools.document-sequences.index'));
        $response->assertStatus(200);
        $response->assertSee('Document Sequences');
        $response->assertSee('Sales Bill / POS Invoice');
        $response->assertSee('Dynamic Tokens');
        $response->assertSee('{YEAR}');
        $response->assertSee('{FY}');
    }

    public function test_user_can_update_document_sequence(): void
    {
        $payload = [
            'document_type'   => 'sales_bill',
            'prefix'          => 'MOT-{FY}-',
            'suffix'          => '',
            'padding_zeros'   => 4,
            'starting_number' => 101,
            'reset_frequency' => 'financial_year',
            'branch_id'       => null,
            'is_active'       => 1,
        ];

        $response = $this->actingAs($this->user)->post(route('tools.document-sequences.update'), $payload);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $sequence = DocumentSequence::where('document_type', 'sales_bill')->whereNull('branch_id')->first();
        $this->assertNotNull($sequence);
        $this->assertEquals('MOT-{FY}-', $sequence->prefix);
        $this->assertEquals(101, $sequence->starting_number);
        $this->assertEquals('financial_year', $sequence->reset_frequency);
    }

    public function test_numbering_service_resolves_tokens_and_increments(): void
    {
        /** @var DocumentNumberingService $service */
        $service = app(DocumentNumberingService::class);

        // Configure custom sequence
        DocumentSequence::create([
            'document_type'     => 'sales_bill',
            'document_title'    => 'Sales Bill',
            'prefix'            => 'UP-{BRANCH}-{YEAR}-',
            'suffix'            => null,
            'padding_zeros'     => 4,
            'starting_number'   => 1,
            'last_number'       => 0,
            'reset_frequency'   => 'financial_year',
            'branch_id'         => $this->branch->id,
            'is_active'         => true,
            'series'            => 'doc:sales_bill:' . $this->branch->id,
        ]);

        $first = $service->generate('sales_bill', $this->branch->id);
        $expectedYear = now()->format('Y');
        $this->assertEquals("UP-MOT-{$expectedYear}-0001", $first);

        $second = $service->generate('sales_bill', $this->branch->id);
        $this->assertEquals("UP-MOT-{$expectedYear}-0002", $second);
    }

    public function test_numbering_service_avoids_collision_with_existing_bills(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'branch_id' => $this->branch->id,
            'status' => 1,
        ]);

        $year = now()->format('Y');

        // Existing bill #1 and #2 already exist
        SalesBill::create([
            'bill_number'    => "SB-{$year}-0001",
            'bill_date'      => now(),
            'branch_id'      => $this->branch->id,
            'customer_id'    => $customer->id,
            'user_id'        => $this->user->id,
            'sales_type'     => 'Retail Sales',
            'payment_type'   => 'Cash',
            'sub_total'      => 100,
            'total_gst'      => 0,
            'total_cgst'     => 0,
            'total_sgst'     => 0,
            'disc_amount'    => 0,
            'round_off'      => 0,
            'total'          => 100,
            'paid_amount'    => 100,
            'due_amount'     => 0,
            'status'         => 'Paid',
        ]);

        SalesBill::create([
            'bill_number'    => "SB-{$year}-0002",
            'bill_date'      => now(),
            'branch_id'      => $this->branch->id,
            'customer_id'    => $customer->id,
            'user_id'        => $this->user->id,
            'sales_type'     => 'Retail Sales',
            'payment_type'   => 'Cash',
            'sub_total'      => 100,
            'total_gst'      => 0,
            'total_cgst'     => 0,
            'total_sgst'     => 0,
            'disc_amount'    => 0,
            'round_off'      => 0,
            'total'          => 100,
            'paid_amount'    => 100,
            'due_amount'     => 0,
            'status'         => 'Paid',
        ]);

        /** @var DocumentNumberingService $service */
        $service = app(DocumentNumberingService::class);

        // Sequence should detect existing bills and generate #3
        $generated = $service->generate('sales_bill', $this->branch->id);
        $this->assertEquals("SB-{$year}-0003", $generated);
    }

    public function test_reset_endpoint_resets_counter(): void
    {
        $sequence = DocumentSequence::create([
            'document_type'     => 'sales_quotation',
            'document_title'    => 'Sales Quotation',
            'prefix'            => 'SQ-{YEAR}-',
            'padding_zeros'     => 4,
            'starting_number'   => 1,
            'last_number'       => 55,
            'reset_frequency'   => 'yearly',
            'branch_id'         => null,
            'is_active'         => true,
            'series'            => 'doc:sales_quotation',
        ]);

        $response = $this->actingAs($this->user)->post(route('tools.document-sequences.reset', $sequence));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $sequence->refresh();
        $this->assertEquals(0, $sequence->last_number);
    }
}
