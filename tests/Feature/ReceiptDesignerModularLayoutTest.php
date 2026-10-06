<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ReceiptSetting;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\User;
use App\Services\Accounting\NumberToWords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptDesignerModularLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name'       => 'Urban Pets Main',
            'code'       => 'UP-MAIN',
            'address'    => 'Shop 4 & 5, Rivera Arcade, Motera, Ahmedabad - 380005',
            'phone'      => '7383056626',
            'email'      => 'support@urbanpets.in',
            'gst_number' => '24AABCU1234F1Z5',
            'status'     => 1,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_number_to_words_converts_indian_currency(): void
    {
        $this->assertEquals(
            'Rupees Two Thousand Six Hundred Sixty Only',
            NumberToWords::toIndianCurrency(2660.00)
        );

        $this->assertEquals(
            'Rupees Nine Thousand One Hundred Twenty Six and Sixty Paise Only',
            NumberToWords::toIndianCurrency(9126.60)
        );

        $this->assertEquals(
            'Rupees One Lakh Twenty Five Thousand Only',
            NumberToWords::toIndianCurrency(125000)
        );

        $this->assertEquals(
            'Rupees Zero Only',
            NumberToWords::toIndianCurrency(0)
        );
    }

    public function test_user_can_save_modular_a4_gst_receipt_settings(): void
    {
        $payload = [
            'document_type'          => 'sales_bill',
            'invoice_format'         => 'a4_gst',
            'header_layout'          => 'logo_left_address_right',
            'accent_color'           => '#047857',
            'store_name'             => 'URBAN PETS SUPERSTORE',
            'tagline'                => 'India Leading Pet Care Chain',
            'show_logo'              => '1',
            'logo_width'             => 130,
            'header_address'         => "Shop 1-4, Rivera Complex, Ahmedabad - 380005",
            'phone'                  => '7383056626',
            'phone_alt'              => '9876543210',
            'email'                  => 'contact@urbanpets.in',
            'gstin'                  => '24ABCDE1234F1Z5',
            'show_customer_pet_name' => '1',
            'show_ship_to'           => '1',
            'show_hsn_code'          => '1',
            'show_tax_breakup'       => '1',
            'show_tax_summary_table' => '1',
            'show_discount'          => '1',
            'show_payment_details'   => '1',
            'show_upi_qr'            => '1',
            'upi_id'                 => 'urbanpets@upi',
            'upi_payee_name'         => 'Urban Pets Superstore',
            'show_barcode'           => '1',
            'show_signature_box'     => '1',
            'show_support_qr'        => '1',
            'support_qr_payload'     => 'https://wa.me/917383056626',
            'paper_size'             => 'a4',
            'font_size'              => 'normal',
            'compliance_notes'       => 'Whether tax is payable on reverse charge: NO',
            'terms_conditions'       => '1. Exchange valid for 7 days with bill. 2. Subject to Ahmedabad jurisdiction.',
            'footer_policy'          => 'No cash refunds.',
            'footer_note'            => 'Thank you for shopping with us!',
        ];

        $response = $this->actingAs($this->user)->post(route('tools.receipt-designer.update'), $payload);
        $response->assertRedirect(route('tools.receipt-designer.index', ['doc' => 'sales_bill']));
        $response->assertSessionHas('success');

        $settings = ReceiptSetting::current();
        $this->assertEquals('a4_gst', $settings->invoice_format);
        $this->assertEquals('logo_left_address_right', $settings->header_layout);
        $this->assertEquals('#047857', $settings->accent_color);
        $this->assertEquals('URBAN PETS SUPERSTORE', $settings->store_name);
        $this->assertTrue($settings->show_ship_to);
        $this->assertTrue($settings->show_tax_summary_table);
        $this->assertTrue($settings->show_payment_details);
        $this->assertTrue($settings->show_signature_box);
        $this->assertTrue($settings->show_support_qr);
        $this->assertEquals('https://wa.me/917383056626', $settings->support_qr_payload);
        $this->assertStringContainsString('reverse charge: NO', $settings->compliance_notes);
        $this->assertStringContainsString('Ahmedabad jurisdiction', $settings->terms_conditions);
    }

    public function test_receipt_renders_modular_a4_gst_invoice_when_a4_format_active(): void
    {
        $settings = ReceiptSetting::current($this->branch->id);
        $settings->update([
            'invoice_format'         => 'a4_gst',
            'header_layout'          => 'logo_left_address_below',
            'accent_color'           => '#1e40af',
            'store_name'             => 'URBAN PETS MEGASTORE',
            'tagline'                => 'Complete Pet Destination',
            'header_address'         => 'Rivera Arcade, Motera, Ahmedabad',
            'phone'                  => '7383056626',
            'gstin'                  => '24AABCU1234F1Z5',
            'show_ship_to'           => true,
            'show_tax_summary_table' => true,
            'show_payment_details'   => true,
            'show_signature_box'     => true,
            'show_support_qr'        => true,
            'support_qr_payload'     => 'https://wa.me/917383056626',
            'compliance_notes'       => 'Whether tax is payable on reverse charge basis: NO',
            'terms_conditions'       => '1. Goods once sold will not be exchanged without original tax invoice.',
        ]);

        $customer = Customer::create([
            'name'      => 'Pooja Patel',
            'phone'     => '9876501234',
            'address'   => 'Satellite, Ahmedabad',
            'city'      => 'Ahmedabad',
            'state'     => 'Gujarat',
            'branch_id' => $this->branch->id,
            'status'    => 1,
        ]);

        $item = Item::create([
            'name'       => 'Royal Canin Puppy 4kg',
            'item_code'  => 'RC-PUP-04',
            'hsn_code'   => '23091000',
            'cost_price' => 1800.00,
            'sell_price' => 2400.00,
            'mrp'        => 2400.00,
            'status'     => true,
        ]);

        $salesBill = SalesBill::create([
            'bill_number'    => 'SB-A4-001',
            'bill_date'      => now(),
            'branch_id'      => $this->branch->id,
            'customer_id'    => $customer->id,
            'user_id'        => $this->user->id,
            'sales_type'     => 'Local',
            'payment_type'   => 'Cash',
            'sub_total'      => 2400.00,
            'total_gst'      => 366.10,
            'total_cgst'     => 183.05,
            'total_sgst'     => 183.05,
            'disc_amount'    => 0,
            'round_off'      => 0,
            'total'          => 2400.00,
            'paid_amount'    => 2400.00,
            'due_amount'     => 0,
            'status'         => 'Posted',
            'invoice_type'   => 'TAX INVOICE',
        ]);

        SalesBillItem::create([
            'sales_bill_id'  => $salesBill->id,
            'item_id'        => $item->id,
            'qty'            => 1,
            'cost_price'     => 1800.00,
            'sell_price'     => 2400.00,
            'mrp'            => 2400.00,
            'gross_amount'   => 2400.00,
            'disc_amount'    => 0,
            'gst_percent'    => 18,
            'gst_tax_amount' => 366.10,
            'cgst_amount'    => 183.05,
            'sgst_amount'    => 183.05,
            'igst_amount'    => 0,
            'net_amount'     => 2400.00,
        ]);

        $response = $this->actingAs($this->user)->get(route('sales.sales-bills.receipt', $salesBill));
        $response->assertStatus(200);

        // Modular A4 GST Tax Invoice elements
        $response->assertSee('a4-gst-invoice-root');
        $response->assertSee('TAX INVOICE');
        $response->assertSee('URBAN PETS MEGASTORE');
        $response->assertSee('Pooja Patel');
        $response->assertSee('23091000'); // HSN code
        $response->assertSee('Tax Summary (By GST Rate)');
        $response->assertSee('Rupees Two Thousand Four Hundred Only');
        $response->assertSee('Details of Consignee | Ship To:');
        $response->assertSee('Authorised Signatory');
        $response->assertSee('Whether tax is payable on reverse charge basis: NO');
        $response->assertSee('Thermal Slip'); // Format switcher button to thermal
    }

    public function test_logo_left_address_right_renders_invoice_details_beside_bill_to_and_omits_ship_to(): void
    {
        $settings = ReceiptSetting::current($this->branch->id);
        $settings->update([
            'invoice_format'         => 'a4_gst',
            'header_layout'          => 'logo_left_address_right',
            'accent_color'           => '#047857',
            'store_name'             => 'URBAN PETS SUPERSTORE',
            'header_address'         => "Shop 1-4, Rivera Complex, Ahmedabad - 380005",
            'phone'                  => '7383056626',
            'gstin'                  => '24ABCDE1234F1Z5',
            'show_ship_to'           => true, // Even if true, logo_left_address_right uses Invoice Details card in that slot
            'show_tax_summary_table' => true,
        ]);

        $customer = Customer::create([
            'name'      => 'Vikram Mehta',
            'phone'     => '9825098250',
            'address'   => 'Navrangpura, Ahmedabad',
            'branch_id' => $this->branch->id,
            'status'    => 1,
        ]);

        $salesBill = SalesBill::create([
            'bill_number'    => 'EINV-TEST-1791271882',
            'bill_date'      => now(),
            'branch_id'      => $this->branch->id,
            'customer_id'    => $customer->id,
            'user_id'        => $this->user->id,
            'sales_type'     => 'Local',
            'payment_type'   => 'Cash',
            'sub_total'      => 1000.00,
            'total_gst'      => 180.00,
            'total'          => 1180.00,
            'paid_amount'    => 1180.00,
            'status'         => 'Posted',
        ]);

        $response = $this->actingAs($this->user)->get(route('sales.sales-bills.receipt', $salesBill));
        $response->assertStatus(200);

        // Section 1 Header & Banner
        $response->assertSee('URBAN PETS SUPERSTORE');
        $response->assertSee('TAX INVOICE');

        // Section 2 Party & Invoice Details
        $response->assertSee('Details of Receiver | Bill To:');
        $response->assertSee('Vikram Mehta');
        $response->assertSee('Invoice Details:');
        $response->assertSee('EINV-TEST-1791271882');
        $response->assertSee('Place of Supply:');

        // Consignee / Ship To must NOT appear in this layout
        $response->assertDontSee('Details of Consignee | Ship To:');
    }

    public function test_user_can_switch_format_via_url_query(): void
    {
        $customer = Customer::create([
            'name'      => 'Walk-in',
            'phone'     => '9999999999',
            'branch_id' => $this->branch->id,
            'status'    => 1,
        ]);

        $salesBill = SalesBill::create([
            'bill_number'    => 'SB-FMT-002',
            'bill_date'      => now(),
            'branch_id'      => $this->branch->id,
            'customer_id'    => $customer->id,
            'user_id'        => $this->user->id,
            'sales_type'     => 'Local',
            'payment_type'   => 'Cash',
            'sub_total'      => 500.00,
            'total_gst'      => 0,
            'total'          => 500.00,
            'paid_amount'    => 500.00,
            'status'         => 'Posted',
        ]);

        // Forced A4 format via URL ?format=a4
        $responseA4 = $this->actingAs($this->user)->get(route('sales.sales-bills.receipt', ['salesBill' => $salesBill, 'format' => 'a4']));
        $responseA4->assertStatus(200);
        $responseA4->assertSee('a4-gst-invoice-root');

        // Forced Thermal format via URL ?format=thermal
        $responseThermal = $this->actingAs($this->user)->get(route('sales.sales-bills.receipt', ['salesBill' => $salesBill, 'format' => 'thermal']));
        $responseThermal->assertStatus(200);
        $responseThermal->assertDontSee('a4-gst-invoice-root');
    }
}
