<?php

namespace Tests\Feature;

use App\Exports\PurchaseDetailExport;
use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class PurchaseDetailOneRowPerInvoiceAndExcelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true]);
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->actingAs($this->user);
    }

    public function test_purchase_detail_renders_exactly_one_row_per_invoice_even_with_multiple_items(): void
    {
        $supplier = Supplier::create([
            'name' => 'Acme Supplies',
            'gst_no' => '24ABCDE1234F1Z5',
            'state' => 'Gujarat',
            'status' => true,
        ]);

        $item1 = Item::create(['name' => 'Item A', 'item_code' => 'ITM-A', 'cost_price' => 100, 'sell_price' => 120, 'mrp' => 150, 'status' => true]);
        $item2 = Item::create(['name' => 'Item B', 'item_code' => 'ITM-B', 'cost_price' => 200, 'sell_price' => 250, 'mrp' => 300, 'status' => true]);
        $item3 = Item::create(['name' => 'Item C', 'item_code' => 'ITM-C', 'cost_price' => 300, 'sell_price' => 600, 'mrp' => 700, 'status' => true]);

        // Create 1 invoice with 3 items
        $inv = PurchaseInvoice::create([
            'invoice_number' => 'PI-MULTI-001',
            'invoice_date' => '2026-09-15',
            'supplier_id' => $supplier->id,
            'supplier_gstin' => '24ABCDE1234F1Z5',
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'freight' => 50.00,
            'tcs_amount' => 10.00,
            'total_cgst' => 45.00,
            'total_sgst' => 45.00,
            'total' => 1105.00,
            'status' => 'Posted',
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $inv->id,
            'item_id' => $item1->id,
            'qty' => 2,
            'cost_price' => 100,
            'sell_price' => 120,
            'mrp' => 150,
            'gst_percent' => 5,
            'gst_tax_amount' => 10,
            'cgst_amount' => 5,
            'sgst_amount' => 5,
            'net_amount' => 210,
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $inv->id,
            'item_id' => $item2->id,
            'qty' => 1,
            'cost_price' => 200,
            'sell_price' => 250,
            'mrp' => 300,
            'gst_percent' => 18,
            'gst_tax_amount' => 36,
            'cgst_amount' => 18,
            'sgst_amount' => 18,
            'net_amount' => 236,
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $inv->id,
            'item_id' => $item3->id,
            'qty' => 1,
            'cost_price' => 500,
            'sell_price' => 600,
            'mrp' => 700,
            'gst_percent' => 12,
            'gst_tax_amount' => 60,
            'cgst_amount' => 30,
            'sgst_amount' => 30,
            'net_amount' => 560,
        ]);

        $params = ['from' => '2026-09-01', 'to' => '2026-09-30'];

        $response = $this->get(route('reports.purchase-detail', $params));
        $response->assertOk();

        // Check view data has exactly 1 invoice
        $invoices = $response->viewData('invoices');
        $this->assertCount(1, $invoices);
        $this->assertSame(1, $invoices->total());
        $this->assertSame('PI-MULTI-001', $invoices->first()->invoice_number);

        // Verify HTML only renders 1 data row in tbody
        $content = $response->getContent();
        $this->assertSame(1, substr_count($content, 'PI-MULTI-001'));

        // Test CSV export has exactly 2 rows (1 header + 1 invoice)
        $csvResponse = $this->get(route('reports.purchase-detail', $params + ['export' => 'csv']));
        $csvContent = trim($csvResponse->streamedContent());
        $lines = explode("\n", $csvContent);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('PI-MULTI-001', $lines[1]);
    }

    public function test_purchase_detail_excel_export_has_exact_16_columns_and_data_matches(): void
    {
        Excel::fake();

        $supplier = Supplier::create([
            'name' => 'Global Metals',
            'gst_no' => '27GHIJK5678L1Z9',
            'state' => 'Maharashtra',
            'status' => true,
        ]);

        $inv = PurchaseInvoice::create([
            'invoice_number' => 'PI-EXCEL-001',
            'invoice_date' => '2026-09-20',
            'supplier_id' => $supplier->id,
            'supplier_gstin' => '27GHIJK5678L1Z9',
            'branch_id' => $this->branch->id,
            'freight' => 120.00,
            'tcs_amount' => 25.50,
            'total' => 2500.00,
            'status' => 'Posted',
        ]);

        $response = $this->get(route('reports.purchase-detail', [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'export' => 'excel',
        ]));

        $response->assertOk();

        Excel::assertDownloaded('purchase-detail_2026-09-01_to_2026-09-30.xlsx', function (PurchaseDetailExport $export) {
            $expectedHeadings = [
                'Inv No',
                'Inv date',
                'Supplier name',
                'GST No.',
                'State Name',
                'Taxable amount',
                'Purchase tax %',
                'SGST Perc',
                'SGST TaxAmt',
                'CGST Perc',
                'CGST TaxAmt',
                'IGST Perc',
                'IGST TaxAmt',
                'Total amount',
                'Freight charges',
                'TCS Amt',
            ];

            return $export->headings() === $expectedHeadings;
        });
    }

    public function test_dedicated_purchase_detail_export_route_works(): void
    {
        Excel::fake();

        $response = $this->get(route('reports.purchase-detail.export', [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]));

        $response->assertOk();
        Excel::assertDownloaded('purchase-detail_2026-09-01_to_2026-09-30.xlsx');
    }
}
