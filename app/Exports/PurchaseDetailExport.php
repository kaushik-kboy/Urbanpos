<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Purchase Detail — invoice-wise Excel export. ONE PURCHASE INVOICE = ONE ROW.
 * Contains exactly the 16 required columns in the requested sequence.
 */
class PurchaseDetailExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    use Exportable;

    public function __construct(
        private readonly string $fromDate,
        private readonly string $toDate,
        private readonly ?int $branchId = null,
        private readonly ?int $supplierId = null,
        private readonly ?string $purchaseType = null,
        private readonly ?string $search = null,
    ) {
    }

    public function query()
    {
        $gstinExpr = "COALESCE(NULLIF(pi.supplier_gstin, ''), NULLIF(s.gst_no, ''))";

        return DB::table('purchase_invoices as pi')
            ->leftJoin('purchase_invoice_items as pii', 'pii.purchase_invoice_id', '=', 'pi.id')
            ->leftJoin('suppliers as s', 's.id', '=', 'pi.supplier_id')
            ->where('pi.invoice_date', '>=', $this->fromDate . ' 00:00:00')
            ->where('pi.invoice_date', '<=', $this->toDate . ' 23:59:59')
            ->where(fn ($q) => $q->whereNull('pi.status')->orWhere('pi.status', '!=', 'Cancelled'))
            ->when($this->branchId, fn ($q) => $q->where('pi.branch_id', $this->branchId))
            ->when($this->supplierId, fn ($q) => $q->where('pi.supplier_id', $this->supplierId))
            ->when($this->purchaseType, fn ($q) => $q->where('pi.purchase_type', $this->purchaseType))
            ->when($this->search, function ($q) {
                $search = $this->search;
                $q->where(function ($sq) use ($search) {
                    $sq->where('pi.invoice_number', 'like', "%{$search}%")
                        ->orWhere('pi.supplier_inv_no', 'like', "%{$search}%")
                        ->orWhere('s.name', 'like', "%{$search}%");
                });
            })
            ->groupBy(
                'pi.id', 'pi.invoice_number', 'pi.invoice_date',
                's.name', 'pi.supplier_gstin', 's.gst_no', 's.state',
                'pi.total', 'pi.freight', 'pi.tcs_amount'
            )
            ->orderBy('pi.invoice_date')->orderBy('pi.id')
            ->selectRaw("
                pi.id,
                pi.invoice_number,
                pi.invoice_date,
                COALESCE(s.name, 'Unknown Supplier') as supplier_name,
                {$gstinExpr} as gst_no,
                s.state as state_name,
                SUM(COALESCE(pii.net_amount, 0) - COALESCE(pii.gst_tax_amount, 0)) as taxable_amount,
                GROUP_CONCAT(DISTINCT CASE WHEN pii.gst_percent IS NOT NULL THEN pii.gst_percent END) as tax_percent_list,
                GROUP_CONCAT(DISTINCT CASE WHEN pii.sgst_amount > 0 THEN pii.gst_percent / 2.0 END) as sgst_percent_list,
                SUM(COALESCE(pii.sgst_amount, 0)) as sgst_tax_amt,
                GROUP_CONCAT(DISTINCT CASE WHEN pii.cgst_amount > 0 THEN pii.gst_percent / 2.0 END) as cgst_percent_list,
                SUM(COALESCE(pii.cgst_amount, 0)) as cgst_tax_amt,
                GROUP_CONCAT(DISTINCT CASE WHEN pii.igst_amount > 0 THEN pii.gst_percent END) as igst_percent_list,
                SUM(COALESCE(pii.igst_amount, 0)) as igst_tax_amt,
                pi.total as total_amount,
                pi.freight as freight_charges,
                pi.tcs_amount as tcs_amt
            ");
    }

    public function headings(): array
    {
        return [
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
    }

    public function map($row): array
    {
        $fmt = fn (float $v) => number_format(round($v, 2), 2, '.', '');

        $trimPercent = function (?string $csv): string {
            if ($csv === null || $csv === '') {
                return '';
            }

            $values = array_map('floatval', explode(',', $csv));
            sort($values, SORT_NUMERIC);

            return implode(', ', array_map(
                fn ($p) => rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.'),
                $values
            ));
        };

        return [
            $row->invoice_number,
            ExcelDate::PHPToExcel(strtotime($row->invoice_date)),
            $row->supplier_name,
            $row->gst_no ?: '',
            $row->state_name ?: '',
            $fmt((float) $row->taxable_amount),
            $trimPercent($row->tax_percent_list),
            $trimPercent($row->sgst_percent_list),
            $fmt((float) $row->sgst_tax_amt),
            $trimPercent($row->cgst_percent_list),
            $fmt((float) $row->cgst_tax_amt),
            $trimPercent($row->igst_percent_list),
            $fmt((float) $row->igst_tax_amt),
            $fmt((float) $row->total_amount),
            $fmt((float) $row->freight_charges),
            $fmt((float) $row->tcs_amt),
        ];
    }

    public function columnFormats(): array
    {
        $money = '0.00';

        return [
            'B' => 'dd-mm-yyyy',
            'F' => $money,
            'I' => $money,
            'K' => $money,
            'M' => $money,
            'N' => $money,
            'O' => $money,
            'P' => $money,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF1E293B'],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ];
                $sheet->getStyle('A1:P1')->applyFromArray($headerStyle);
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:P1');
                $sheet->getRowDimension(1)->setRowHeight(26);
            },
        ];
    }
}
