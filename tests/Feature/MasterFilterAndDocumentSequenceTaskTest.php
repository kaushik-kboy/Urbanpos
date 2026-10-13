<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\DocumentNumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterFilterAndDocumentSequenceTaskTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Main Branch',
            'code' => 'MB01',
            'address' => '123 Market Street',
            'phone' => '9876543210',
            'status' => 1,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_item_list_search_without_status_returns_both_active_and_inactive_records(): void
    {
        $supplier1 = Supplier::create([
            'name' => 'Supplier Alpha',
            'phone' => '9999990001',
            'status' => 1,
        ]);

        $supplier2 = Supplier::create([
            'name' => 'Supplier Beta',
            'phone' => '9999990002',
            'status' => 1,
        ]);

        $activeItem = Item::create([
            'name' => 'FilteredWidget 100',
            'item_code' => 'FW-100',
            'supplier_id' => $supplier1->id,
            'status' => 1,
            'cost_price' => 50,
            'selling_price' => 100,
        ]);

        $inactiveItem = Item::create([
            'name' => 'FilteredWidget 200',
            'item_code' => 'FW-200',
            'supplier_id' => $supplier2->id,
            'status' => 0,
            'cost_price' => 60,
            'selling_price' => 120,
        ]);

        // When searching only by name without specifying status, BOTH should be returned
        $response = $this->actingAs($this->user)->get(route('master.items.index', [
            'search' => 'FilteredWidget',
        ]));

        $response->assertStatus(200);
        $response->assertSee('FilteredWidget 100');
        $response->assertSee('FilteredWidget 200');
    }

    public function test_item_list_status_all_and_supplier_all_returns_all_records(): void
    {
        $supplier1 = Supplier::create(['name' => 'Supplier One', 'status' => 1]);
        $supplier2 = Supplier::create(['name' => 'Supplier Two', 'status' => 1]);

        Item::create([
            'name' => 'Gadget Active Sup1',
            'item_code' => 'GAD-01',
            'supplier_id' => $supplier1->id,
            'status' => 1,
        ]);

        Item::create([
            'name' => 'Gadget Inactive Sup2',
            'item_code' => 'GAD-02',
            'supplier_id' => $supplier2->id,
            'status' => 0,
        ]);

        $response = $this->actingAs($this->user)->get(route('master.items.index', [
            'search' => 'Gadget',
            'supplier_id' => 'all',
            'status' => 'all',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Gadget Active Sup1');
        $response->assertSee('Gadget Inactive Sup2');
    }

    public function test_item_list_status_active_only_and_inactive_only_filters(): void
    {
        $activeItem = Item::create([
            'name' => 'Device Active',
            'item_code' => 'DEV-ACT',
            'status' => 1,
        ]);

        $inactiveItem = Item::create([
            'name' => 'Device Inactive',
            'item_code' => 'DEV-INACT',
            'status' => 0,
        ]);

        // Filter status=1 (Active)
        $resActive = $this->actingAs($this->user)->get(route('master.items.index', [
            'search' => 'Device',
            'status' => '1',
        ]));
        $resActive->assertStatus(200);
        $resActive->assertSee('Device Active');
        $resActive->assertDontSee('Device Inactive');

        // Filter status=0 (Inactive)
        $resInactive = $this->actingAs($this->user)->get(route('master.items.index', [
            'search' => 'Device',
            'status' => '0',
        ]));
        $resInactive->assertStatus(200);
        $resInactive->assertSee('Device Inactive');
        $resInactive->assertDontSee('Device Active');
    }

    public function test_customer_list_status_all_and_specific_status(): void
    {
        Customer::create(['name' => 'Cust Active', 'phone' => '9888800001', 'status' => 1]);
        Customer::create(['name' => 'Cust Inactive', 'phone' => '9888800002', 'status' => 0]);

        // Blank or status=all
        $resAll = $this->actingAs($this->user)->get(route('master.customers.index', [
            'search' => 'Cust',
            'status' => 'all',
        ]));
        $resAll->assertStatus(200);
        $resAll->assertSee('Cust Active');
        $resAll->assertSee('Cust Inactive');

        // Status=0
        $resInact = $this->actingAs($this->user)->get(route('master.customers.index', [
            'search' => 'Cust',
            'status' => '0',
        ]));
        $resInact->assertStatus(200);
        $resInact->assertSee('Cust Inactive');
        $resInact->assertDontSee('Cust Active');
    }

    public function test_supplier_list_status_all_and_specific_status(): void
    {
        Supplier::create(['name' => 'Sup Active', 'phone' => '9777700001', 'status' => 1]);
        Supplier::create(['name' => 'Sup Inactive', 'phone' => '9777700002', 'status' => 0]);

        $resAll = $this->actingAs($this->user)->get(route('master.suppliers.index', [
            'search' => 'Sup',
            'status' => 'all',
        ]));
        $resAll->assertStatus(200);
        $resAll->assertSee('Sup Active');
        $resAll->assertSee('Sup Inactive');

        $resAct = $this->actingAs($this->user)->get(route('master.suppliers.index', [
            'search' => 'Sup',
            'status' => '1',
        ]));
        $resAct->assertStatus(200);
        $resAct->assertSee('Sup Active');
        $resAct->assertDontSee('Sup Inactive');
    }

    public function test_document_sequences_work_for_all_document_types(): void
    {
        $docTypes = [
            'purchase_invoice'       => 'PINV-CUSTOM-',
            'purchase_receipt_note'  => 'PRN-CUSTOM-',
            'purchase_order'         => 'PO-CUSTOM-',
            'purchase_return'        => 'PRET-CUSTOM-',
            'purchase_indent'        => 'IND-CUSTOM-',
            'stock_transfer'         => 'STRF-CUSTOM-',
            'stock_update'           => 'STUP-CUSTOM-',
            'damage_stock'           => 'DMG-CUSTOM-',
            'opening_stock'          => 'OPST-CUSTOM-',
            'voucher_payment'        => 'VPAY-CUSTOM-',
            'voucher_receipt'        => 'VRCT-CUSTOM-',
            'voucher_journal'        => 'VJRN-CUSTOM-',
            'voucher_contra'         => 'VCNT-CUSTOM-',
        ];

        foreach ($docTypes as $docType => $prefix) {
            DocumentSequence::create([
                'document_type'   => $docType,
                'prefix'          => $prefix,
                'suffix'          => '',
                'padding_zeros'   => 4,
                'starting_number' => 10,
                'reset_frequency' => 'never',
                'branch_id'       => null,
                'is_active'       => 1,
            ]);
        }

        $service = app(DocumentNumberingService::class);

        foreach ($docTypes as $docType => $prefix) {
            $generated = $service->generate($docType, $this->branch->id, '2026-10-14');
            $this->assertStringStartsWith($prefix, $generated, "Document sequence for {$docType} did not start with custom prefix");
            $this->assertStringEndsWith('0010', $generated, "Document sequence for {$docType} did not end with padded starting number");
        }
    }
}
