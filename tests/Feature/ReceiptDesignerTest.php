<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPet;
use App\Models\Item;
use App\Models\ReceiptSetting;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptDesignerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Motera Branch',
            'code' => 'MOT-1',
            'address' => 'Motera Stadium Road',
            'phone' => '7383056626',
            'gst_number' => '24AABCU1234F1Z5',
            'status' => 1,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_guest_cannot_access_receipt_designer(): void
    {
        $response = $this->get(route('tools.receipt-designer.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_user_can_access_receipt_designer_ui(): void
    {
        $response = $this->actingAs($this->user)->get(route('tools.receipt-designer.index'));
        $response->assertStatus(200);
        $response->assertSee('Invoice Print Designer');
        $response->assertSee('Store Header');
        $response->assertSee('Dynamic UPI Payment QR Code');
    }

    public function test_user_can_update_receipt_settings(): void
    {
        $payload = [
            'store_name'             => 'URBAN PETS & CLINIC',
            'tagline'                => 'Veterinary Care & Pet Supermarket',
            'show_logo'              => '1',
            'logo_width'             => 140,
            'header_address'         => "Shop 1-2, Rivera Complex,\nMotera, Ahmedabad - 380005",
            'phone'                  => '9876543210',
            'phone_alt'              => '9123456780',
            'email'                  => 'care@urbanpets.in',
            'gstin'                  => '24ABCDE1234F1Z9',
            'show_customer_pet_name' => '1',
            'show_hsn_code'          => '0', // toggled off
            'show_tax_breakup'       => '1',
            'show_discount'          => '1',
            'show_upi_qr'            => '1',
            'upi_id'                 => 'urbanpets@icici',
            'upi_payee_name'         => 'Urban Pets Ahmedabad',
            'show_barcode'           => '1',
            'paper_size'             => '58mm',
            'font_size'              => 'small',
            'footer_policy'          => "No exchange on vaccines.\nValid within 3 days.",
            'footer_note'            => 'Thank you for visiting! Stay healthy!',
        ];

        $response = $this->actingAs($this->user)->post(route('tools.receipt-designer.update'), $payload);
        $response->assertRedirect(route('tools.receipt-designer.index', ['doc' => 'sales_bill']));
        $response->assertSessionHas('success');

        $settings = ReceiptSetting::current();
        $this->assertEquals('URBAN PETS & CLINIC', $settings->store_name);
        $this->assertEquals('Veterinary Care & Pet Supermarket', $settings->tagline);
        $this->assertEquals('9876543210', $settings->phone);
        $this->assertEquals('9123456780', $settings->phone_alt);
        $this->assertEquals('urbanpets@icici', $settings->upi_id);
        $this->assertFalse($settings->show_hsn_code);
        $this->assertTrue($settings->show_upi_qr);
        $this->assertEquals('58mm', $settings->paper_size);
        $this->assertEquals('small', $settings->font_size);
        $this->assertStringContainsString('No exchange on vaccines', $settings->footer_policy);
    }

    public function test_user_can_switch_tabs_and_update_stock_transfer_settings(): void
    {
        $response = $this->actingAs($this->user)->get(route('tools.receipt-designer.index', ['doc' => 'stock_transfer']));
        $response->assertStatus(200);
        $response->assertSee('Stock Transfer Note Print Designer');
        $response->assertSee('STOCK TRANSFER NOTE');

        $payload = [
            'document_type'  => 'stock_transfer',
            'store_name'     => 'URBAN PETS LOGISTICS HUB',
            'tagline'        => 'Inter-Branch Stock Movement',
            'paper_size'     => 'a4',
            'font_size'      => 'normal',
            'footer_policy'  => 'Inspect all items immediately upon delivery.',
            'footer_note'    => 'Official Transfer Challan',
        ];

        $response = $this->actingAs($this->user)->post(route('tools.receipt-designer.update'), $payload);
        $response->assertRedirect(route('tools.receipt-designer.index', ['doc' => 'stock_transfer']));
        $response->assertSessionHas('success');

        $transferSettings = ReceiptSetting::forDocument('stock_transfer');
        $this->assertEquals('URBAN PETS LOGISTICS HUB', $transferSettings->store_name);
        $this->assertEquals('a4', $transferSettings->paper_size);
        $this->assertEquals('Official Transfer Challan', $transferSettings->footer_note);

        // Sales bill settings must remain untouched
        $salesSettings = ReceiptSetting::forDocument('sales_bill');
        $this->assertNotEquals('URBAN PETS LOGISTICS HUB', $salesSettings->store_name);
    }

    public function test_receipt_renders_dynamic_customizer_settings(): void
    {
        // Configure custom settings
        $settings = ReceiptSetting::current();
        $settings->update([
            'store_name'             => 'CUSTOM PET HAVEN',
            'tagline'                => 'Best Pets Around',
            'header_address'         => "100 Express Way, Ahmedabad",
            'phone'                  => '9998887776',
            'show_customer_pet_name' => true,
            'show_hsn_code'          => true,
            'show_upi_qr'            => true,
            'upi_id'                 => 'customhaven@upi',
            'paper_size'             => '80mm',
            'footer_policy'          => 'Strict 5 day return on accessories only.',
            'footer_note'            => 'Have a magical day with your pet!',
        ]);

        $customer = Customer::create([
            'name' => 'Rahul Sharma',
            'phone' => '9988776655',
            'branch_id' => $this->branch->id,
            'status' => 1,
        ]);

        CustomerPet::create([
            'customer_id' => $customer->id,
            'name' => 'Charlie',
        ]);

        $item = Item::create([
            'name' => 'Dog Leash Red',
            'item_code' => 'DOG-LSH-RD',
            'hsn_code' => '42010000',
            'cost_price' => 300.00,
            'sell_price' => 450.00,
            'mrp' => 450.00,
            'status' => true,
        ]);

        $salesBill = SalesBill::create([
            'bill_number'    => 'SB-TEST-001',
            'bill_date'      => now(),
            'branch_id'      => $this->branch->id,
            'customer_id'    => $customer->id,
            'user_id'        => $this->user->id,
            'sales_type'     => 'Retail Sales',
            'payment_type'   => 'Cash',
            'sub_total'      => 450.00,
            'total_gst'      => 81.00,
            'total_cgst'     => 40.50,
            'total_sgst'     => 40.50,
            'disc_amount'    => 0,
            'round_off'      => 0,
            'total'          => 450.00,
            'paid_amount'    => 450.00,
            'due_amount'     => 0,
            'status'         => 'Paid',
            'invoice_type'   => 'TAX INVOICE',
        ]);

        SalesBillItem::create([
            'sales_bill_id' => $salesBill->id,
            'item_id'       => $item->id,
            'qty'           => 1,
            'cost_price'    => 300.00,
            'sell_price'    => 450.00,
            'mrp'           => 450.00,
            'gross_amount'  => 450.00,
            'disc_amount'   => 0,
            'gst_percent'   => 18,
            'cgst_amount'   => 40.50,
            'sgst_amount'   => 40.50,
            'igst_amount'   => 0,
            'net_amount'    => 450.00,
        ]);

        $response = $this->actingAs($this->user)->get(route('sales.sales-bills.receipt', $salesBill));
        $response->assertStatus(200);

        // Assert dynamic customized elements appear
        $response->assertSee('CUSTOM PET HAVEN');
        $response->assertSee('Best Pets Around');
        $response->assertSee('100 Express Way, Ahmedabad');
        $response->assertSee('9998887776');
        $response->assertSee('Charlie'); // pet name
        $response->assertSee('42010000'); // HSN code
        $response->assertSee('SCAN TO PAY VIA UPI');
        $response->assertSee('customhaven@upi');
        $response->assertSee('Strict 5 day return on accessories only.');
        $response->assertSee('Have a magical day with your pet!');
    }
}
