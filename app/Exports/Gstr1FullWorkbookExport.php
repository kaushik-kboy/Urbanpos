<?php

namespace App\Exports;

use App\Services\GST\Gstr1ReportService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class Gstr1FullWorkbookExport implements WithMultipleSheets
{
    public function __construct(
        protected string $fromDate,
        protected string $toDate
    ) {}

    /**
     * @return array<int, Gstr1SectionSheetExport>
     */
    public function sheets(): array
    {
        $service = new Gstr1ReportService($this->fromDate, $this->toDate);
        $sheets = [];

        // Common HSN Headings
        $hsnHeadings = [
            'HSN or SAC code',
            'Item name',
            'UOM',
            'Total Quantity',
            'Total Value (₹)',
            'Rate (%)',
            'Nil/Exempted Value (₹)',
            'Taxable Value (₹)',
            'IGST (₹)',
            'CGST (₹)',
            'SGST (₹)',
            'CESS (₹)',
        ];

        // 1. B2C HSN
        $b2cHsnData = $service->hsnB2c();
        $b2cHsnRows = [];
        foreach ($b2cHsnData['rows'] ?? [] as $r) {
            $b2cHsnRows[] = [
                $r['hsn'] ?? '',
                $r['name'] ?? '',
                $r['uom'] ?? 'NOS',
                $r['qty'] ?? 0,
                $r['total'] ?? 0,
                $r['rate'] ?? 0,
                $r['nil'] ?? 0,
                $r['taxable'] ?? 0,
                $r['igst'] ?? 0,
                $r['cgst'] ?? 0,
                $r['sgst'] ?? 0,
                $r['cess'] ?? 0,
            ];
        }
        $sheets[] = new Gstr1SectionSheetExport('B2C HSN', $hsnHeadings, $b2cHsnRows);

        // 2. B2B HSN
        $b2bHsnData = $service->hsnB2b();
        $b2bHsnRows = [];
        foreach ($b2bHsnData['rows'] ?? [] as $r) {
            $b2bHsnRows[] = [
                $r['hsn'] ?? '',
                $r['name'] ?? '',
                $r['uom'] ?? 'NOS',
                $r['qty'] ?? 0,
                $r['total'] ?? 0,
                $r['rate'] ?? 0,
                $r['nil'] ?? 0,
                $r['taxable'] ?? 0,
                $r['igst'] ?? 0,
                $r['cgst'] ?? 0,
                $r['sgst'] ?? 0,
                $r['cess'] ?? 0,
            ];
        }
        $sheets[] = new Gstr1SectionSheetExport('B2B HSN', $hsnHeadings, $b2bHsnRows);

        // 3. B2B Invoices
        $b2bData = $service->b2b();
        $b2bHeadings = [
            'Invoice No',
            'Invoice Date',
            'Customer Name',
            'GSTIN',
            'Place of Supply',
            'Taxable Value (₹)',
            'IGST (₹)',
            'CGST (₹)',
            'SGST (₹)',
            'Invoice Value (₹)',
        ];
        $b2bRows = [];
        foreach ($b2bData['rows'] ?? [] as $r) {
            $b2bRows[] = [
                $r['ref'] ?? '',
                $r['date'] ?? '',
                $r['customer'] ?? '',
                $r['gstin'] ?? '',
                $r['pos'] ?? '',
                $r['taxable'] ?? 0,
                $r['igst'] ?? 0,
                $r['cgst'] ?? 0,
                $r['sgst'] ?? 0,
                $r['total'] ?? 0,
            ];
        }
        $sheets[] = new Gstr1SectionSheetExport('B2B Invoices', $b2bHeadings, $b2bRows);

        // 4. B2CL Invoices (Large)
        $b2clData = $service->b2cl();
        $b2clHeadings = [
            'Invoice No',
            'Invoice Date',
            'Place of Supply',
            'Taxable Value (₹)',
            'IGST (₹)',
            'Invoice Value (₹)',
        ];
        $b2clRows = [];
        foreach ($b2clData['rows'] ?? [] as $r) {
            $b2clRows[] = [
                $r['ref'] ?? '',
                $r['date'] ?? '',
                $r['pos'] ?? '',
                $r['taxable'] ?? 0,
                $r['igst'] ?? 0,
                $r['total'] ?? 0,
            ];
        }
        $sheets[] = new Gstr1SectionSheetExport('B2CL Invoices', $b2clHeadings, $b2clRows);

        // 5. Exports (EXP)
        $expData = $service->exportSupplies();
        $expHeadings = [
            'Export Type',
            'Invoice Number',
            'Invoice Date',
            'Invoice Value (₹)',
            'Port Code',
            'Shipping Bill No',
            'Shipping Bill Date',
            'Rate (%)',
            'Taxable Value (₹)',
            'Cess (₹)',
        ];
        $expRows = [];
        foreach ($expData['rows'] ?? [] as $r) {
            $expRows[] = [
                $r['export_type'] ?? '',
                $r['ref'] ?? '',
                $r['date'] ?? '',
                $r['total'] ?? 0,
                $r['port_code'] ?? '',
                $r['shipping_bill_no'] ?? '',
                $r['shipping_bill_date'] ?? '',
                $r['rate'] ?? 0,
                $r['taxable'] ?? 0,
                $r['cess'] ?? 0,
            ];
        }
        $sheets[] = new Gstr1SectionSheetExport('Exports (EXP)', $expHeadings, $expRows);

        // 6. B2CS Small
        $b2csData = $service->b2cs();
        $b2csHeadings = [
            'Place of Supply',
            'Rate (%)',
            'Taxable Value (₹)',
            'IGST (₹)',
            'CGST (₹)',
            'SGST (₹)',
        ];
        $b2csRows = [];
        foreach ($b2csData['rows'] ?? [] as $r) {
            $b2csRows[] = [
                $r['pos'] ?? '',
                $r['rate'] ?? 0,
                $r['taxable'] ?? 0,
                $r['igst'] ?? 0,
                $r['cgst'] ?? 0,
                $r['sgst'] ?? 0,
            ];
        }
        $sheets[] = new Gstr1SectionSheetExport('B2CS Small', $b2csHeadings, $b2csRows);

        // 7. CDNR Registered Credit/Debit Notes
        $cdnrData = $service->cdnr();
        $cdnrHeadings = [
            'Note No',
            'Note Date',
            'Original Invoice',
            'Customer Name',
            'GSTIN',
            'GST %',
            'Taxable Value (₹)',
            'IGST (₹)',
            'CGST (₹)',
            'SGST (₹)',
            'Note Value (₹)',
        ];
        $cdnrRows = [];
        foreach ($cdnrData['rows'] ?? [] as $r) {
            $cdnrRows[] = [
                $r['ref'] ?? '',
                $r['date'] ?? '',
                $r['original_invoice'] ?? '',
                $r['customer'] ?? '',
                $r['gstin'] ?? '',
                $r['rate'] ?? '0%',
                $r['taxable'] ?? 0,
                $r['igst'] ?? 0,
                $r['cgst'] ?? 0,
                $r['sgst'] ?? 0,
                $r['total'] ?? 0,
            ];
        }
        $sheets[] = new Gstr1SectionSheetExport('CDNR Registered', $cdnrHeadings, $cdnrRows);

        // 8. CDNUR Unregistered Credit/Debit Notes
        $cdnurData = $service->cdnur();
        $cdnurHeadings = [
            'Note No',
            'Note Date',
            'Original Invoice',
            'Customer Name',
            'GST %',
            'Taxable Value (₹)',
            'IGST (₹)',
            'CGST (₹)',
            'SGST (₹)',
            'Note Value (₹)',
        ];
        $cdnurRows = [];
        foreach ($cdnurData['rows'] ?? [] as $r) {
            $cdnurRows[] = [
                $r['ref'] ?? '',
                $r['date'] ?? '',
                $r['original_invoice'] ?? '',
                $r['customer'] ?? '',
                $r['rate'] ?? '0%',
                $r['taxable'] ?? 0,
                $r['igst'] ?? 0,
                $r['cgst'] ?? 0,
                $r['sgst'] ?? 0,
                $r['total'] ?? 0,
            ];
        }
        $sheets[] = new Gstr1SectionSheetExport('CDNUR Unregistered', $cdnurHeadings, $cdnurRows);

        // 9. Nil Rated
        $nilData = $service->nilRated();
        $nilHeadings = [
            '#',
            'Item Name',
            'HSN Code',
            'Rate (%)',
            'Qty Sold',
            'Bills Count',
            'Nil-Rated Value (₹)',
        ];
        $nilRows = [];
        $i = 1;
        foreach ($nilData['rows'] ?? [] as $r) {
            $nilRows[] = [
                $i++,
                $r['name'] ?? '',
                $r['hsn'] ?? '',
                '0.00%',
                $r['qty'] ?? 0,
                $r['bills'] ?? 0,
                number_format($r['amount'] ?? 0, 2, '.', ''),
            ];
        }
        $sheets[] = new Gstr1SectionSheetExport('Nil Rated', $nilHeadings, $nilRows);

        // 10. Advance Received (Adv Rec)
        $advRecData = $service->advanceReceived();
        $advRecHeadings = [
            'Place of Supply',
            'Applicable % of Tax Rate',
            'Rate (%)',
            'Gross Advance Received (₹)',
            'Cess (₹)',
        ];
        $advRecRows = [];
        foreach ($advRecData['rows'] ?? [] as $r) {
            $advRecRows[] = [
                $r['pos'] ?? '',
                $r['applicable_tax_rate'] ?? '',
                $r['rate'] ?? 0,
                $r['gross_received'] ?? 0,
                $r['cess'] ?? 0,
            ];
        }
        $sheets[] = new Gstr1SectionSheetExport('Adv Received', $advRecHeadings, $advRecRows);

        // 11. Advance Adjusted (Adv Adj)
        $advAdjData = $service->advanceAdjusted();
        $advAdjHeadings = [
            'Place of Supply',
            'Applicable % of Tax Rate',
            'Rate (%)',
            'Gross Advance Adjusted (₹)',
            'Cess (₹)',
        ];
        $advAdjRows = [];
        foreach ($advAdjData['rows'] ?? [] as $r) {
            $advAdjRows[] = [
                $r['pos'] ?? '',
                $r['applicable_tax_rate'] ?? '',
                $r['rate'] ?? 0,
                $r['gross_adjusted'] ?? 0,
                $r['cess'] ?? 0,
            ];
        }
        $sheets[] = new Gstr1SectionSheetExport('Adv Adjusted', $advAdjHeadings, $advAdjRows);

        // 12. Documents Issued
        $docData = $service->documentsIssued();
        $docHeadings = [
            'Branch',
            'Document Series / Type',
            'First No (From)',
            'Last No (To)',
            'Total Issued',
            'Cancelled',
            'Net Issued',
        ];
        $docRows = [];
        foreach ($docData['rows'] ?? [] as $r) {
            $docRows[] = [
                $r['branch'] ?? '',
                $r['doc_type'] ?? ($r['label'] ?? ''),
                $r['first'] ?? '',
                $r['last'] ?? '',
                $r['total'] ?? 0,
                $r['cancelled'] ?? 0,
                $r['net_issued'] ?? 0,
            ];
        }
        $sheets[] = new Gstr1SectionSheetExport('Documents Issued', $docHeadings, $docRows);

        return $sheets;
    }
}
