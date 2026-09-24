<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdvancedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name'                      => 'Main Branch',
            'code'                      => 'MB01',
            'business_type'             => 'Retail',
            'webstore'                  => 0,
            'erp_code'                  => 'ERP01',
            'country_code'              => 'IN',
            'currency_symbol'           => '₹',
            'enable_thirdparty_loyalty' => 0,
            'gst_type'                  => 'Regular',
            'gst_filing'                => 'Monthly',
            'status'                    => 1,
            'upi_id'                    => 'mainbranch@upi',
            'upi_payee_name'            => 'Main Branch Store',
        ]);

        $this->admin = User::factory()->create([
            'branch_id' => $this->branch->id,
            'pos_pin'   => Hash::make('4321'),
        ]);
    }

    public function test_pos_lock_screen_unlocks_with_correct_pin(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('pos.verify-pin'), [
            'pin' => '4321',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_pos_lock_screen_rejects_wrong_pin(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('pos.verify-pin'), [
            'pin' => '0000',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_barcode_label_print_for_single_item(): void
    {
        $item = Item::create([
            'name'                 => 'Pet Shampoo 250ml',
            'item_code'            => 'SHP250',
            'ean_upc_code'         => '8901234567890',
            'product_type'         => 'Standard',
            'cost_price'           => 100,
            'landing_cost'         => 100,
            'sell_price'           => 180,
            'mrp'                  => 200,
            'status'               => 1,
            'store_pickup'         => 0,
            'tax_inclusive'        => 0,
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => 0,
        ]);

        $response = $this->actingAs($this->admin)->get(route('master.barcodes.print', [
            'item_id' => $item->id,
            'qty'     => 3,
            'format'  => '50x25',
        ]));

        $response->assertOk();
        $response->assertSee('Pet Shampoo 250ml');
        $response->assertSee('8901234567890');
    }

    public function test_barcode_label_print_for_purchase_invoice(): void
    {
        $supplier = Supplier::create([
            'name'           => 'Global Pet Care Supplies',
            'code'           => 'SUP-999',
            'credit_balance' => 0,
            'credit_days'    => 30,
            'status'         => 1,
            'gst_type'       => 'Regular',
            'mail_type'      => 'None',
        ]);

        $pi = PurchaseInvoice::create([
            'invoice_number'      => 'PINV-TEST-01',
            'invoice_date'        => now()->toDateString(),
            'branch_id'           => $this->branch->id,
            'supplier_id'         => $supplier->id,
            'status'              => 'Approved',
            'total'               => 500,
            'supplier_inv_amount' => 500,
        ]);

        $item = Item::create([
            'name'                 => 'Chew Toy Large',
            'item_code'            => 'TOY-LRG',
            'ean_upc_code'         => '7891234567891',
            'product_type'         => 'Standard',
            'cost_price'           => 100,
            'landing_cost'         => 100,
            'sell_price'           => 150,
            'mrp'                  => 180,
            'status'               => 1,
            'store_pickup'         => 0,
            'tax_inclusive'        => 0,
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => 0,
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $pi->id,
            'item_id'             => $item->id,
            'qty'                 => 5,
            'cost_price'          => 100,
            'sell_price'          => 150,
            'mrp'                 => 180,
            'total'               => 500,
        ]);

        $response = $this->actingAs($this->admin)->get(route('master.barcodes.print', [
            'purchase_invoice_id' => $pi->id,
            'format'              => '38x25',
        ]));

        $response->assertOk();
        $response->assertSee('Chew Toy Large');
        $response->assertSee('Total: 5 Stickers');

        // Test 102x64 TSC TE244 thermal label format
        $responseTsc = $this->actingAs($this->admin)->get(route('master.barcodes.print', [
            'purchase_invoice_id' => $pi->id,
            'format'              => '102x64',
        ]));
        $responseTsc->assertOk();
        $responseTsc->assertSee('format-102x64');
        $responseTsc->assertSee('102x63.5 mm (TSC TE244)');
    }

    public function test_database_backup_console_management(): void
    {
        $response = $this->actingAs($this->admin)->get(route('tools.backups.index'));
        $response->assertOk();
        $response->assertSee('Database Backups');

        // Trigger backup creation via controller
        $createResponse = $this->actingAs($this->admin)->post(route('tools.backups.create'));
        $createResponse->assertRedirect(route('tools.backups.index'));

        // Check that backup was created
        $backupDir = storage_path('app/backups');
        $files = File::files($backupDir);
        $this->assertNotEmpty($files);

        $latestFile = $files[0]->getFilename();

        // Test download
        $downloadResponse = $this->actingAs($this->admin)->get(route('tools.backups.download', $latestFile));
        $this->assertTrue(in_array($downloadResponse->getStatusCode(), [200, 302]));
    }
}
