<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\User;
use App\Models\WhatsAppSetting;
use App\Services\Accounting\DocumentNumberingService;
use App\Services\WhatsApp\ChatOnClickWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodSequenceAndStockUpdateValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Branch::firstOrCreate(['id' => 1], ['name' => 'Main Branch', 'status' => 1]);
    }

    public function test_cod_bill_sequence_generation(): void
    {
        $branch = Branch::first();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        // Configure a COD Document Sequence
        DocumentSequence::create([
            'document_type'   => 'cod_bill',
            'document_title'  => 'COD Bill / Cash on Delivery',
            'prefix'          => 'COD-{YEAR}-',
            'padding_zeros'   => 4,
            'starting_number' => 1,
            'last_number'     => 0,
            'reset_frequency' => 'financial_year',
            'branch_id'       => null,
            'is_active'       => true,
            'series'          => 'doc:cod_bill',
        ]);

        $customer = Customer::create([
            'name'         => 'COD Client',
            'mobile'       => '9879946696',
            'sales_type'   => 'Local',
            'payment_mode' => 'Cash on Delivery',
            'status'       => 1,
            'gst_type'     => 'Un Register',
            'sms_consent'  => 0,
        ]);

        $item = Item::create([
            'name'                 => 'Dog Food',
            'item_code'            => 'DOG001',
            'sell_price'           => 500.00,
            'mrp'                  => 550.00,
            'status'               => 1,
            'allow_negative_stock' => 1,
        ]);

        $payload = [
            'branch_id'     => $branch->id,
            'customer_id'   => $customer->id,
            'bill_date'     => now()->setTimezone('Asia/Kolkata')->format('Y-m-d H:i:s'),
            'sales_type'    => 'Local',
            'invoice_type'  => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'payment_type'  => 'COD',
            'items' => [
                [
                    'item_id'    => $item->id,
                    'qty'        => 2,
                    'sell_price' => 500.00,
                    'mrp'        => 550.00,
                ],
            ],
        ];

        $response = $this->actingAs($user)->post(route('sales.sales-bills.store'), $payload);
        $response->assertSessionHasNoErrors();

        $latestBill = SalesBill::latest('id')->first();
        $this->assertNotNull($latestBill);
        $year = now()->format('Y');
        $this->assertStringStartsWith("COD-{$year}-", $latestBill->bill_number);
        $this->assertEquals('COD', $latestBill->payment_type);
    }

    public function test_document_numbering_definitions_include_cod(): void
    {
        $defs = DocumentNumberingService::defaultDefinitions();
        $this->assertArrayHasKey('cod_bill', $defs);
        $this->assertEquals('COD Bill / Cash on Delivery', $defs['cod_bill']['title']);
    }
}
