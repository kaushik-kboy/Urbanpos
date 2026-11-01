<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Purchase Detail — invoice-wise Excel export. ONE PURCHASE INVOICE = ONE ROW.
 * Contains 26 GST tax-wise columns (0%, 5%, 18% taxable + IGST/CGST/SGST breakup).
 */
class PurchaseDetailExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(
        private readonly string $fromDate,
        private readonly string $toDate,
        private readonly ?int $branchId = null,
        private readonly ?int $supplierId = null,
        private readonly ?string $purchaseType = null,
        private readonly ?string $search = null,
    ) {}

    public function query()
    {
        $gstinExpr = "COALESCE(NULLIF(pi.supplier_gstin, ''), NULLIF(s.gst_no, ''))";

        return DB::table('purchase_invoices as pi')
            ->leftJoin('purchase_invoice_items as pii', 'pii.purchase_invoice_id', '=', 'pi.id')
            ->leftJoin('suppliers as s', 's.id', '=', 'pi.supplier_id')
            ->where('pi.invoice_date', '>=', $this->fromDate.' 00:00:00')
            ->where('pi.invoice_date', '<=', $this->toDate.' 23:59:59')
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
                'pi.id',
                'pi.invoice_number',
                'pi.supplier_inv_no',
                'pi.invoice_date',
                's.name',
                'pi.supplier_gstin',
                's.gst_no',
                's.state',
                'pi.total'
            )
            ->orderBy('pi.invoice_date')->orderBy('pi.id')
            ->selectRaw("
                pi.id,
                pi.invoice_number,
                pi.supplier_inv_no,
                pi.invoice_date,
                COALESCE(s.name, 'Unknown Supplier') as supplier_name,
                {$gstinExpr} as gst_no,
                s.state as state_name,
                SUM(CASE WHEN pii.gst_percent = 0 THEN (COALESCE(pii.net_amount, 0) - COALESCE(pii.gst_tax_amount, 0)) ELSE 0 END) as taxable_0_amount,
                SUM(CASE WHEN pii.gst_percent = 5 THEN (COALESCE(pii.net_amount, 0) - COALESCE(pii.gst_tax_amount, 0)) ELSE 0 END) as taxable_5_amount,
                SUM(CASE WHEN pii.gst_percent = 18 THEN (COALESCE(pii.net_amount, 0) - COALESCE(pii.gst_tax_amount, 0)) ELSE 0 END) as taxable_18_amount,
                SUM(CASE WHEN pii.gst_percent = 5 THEN COALESCE(pii.igst_amount, 0) ELSE 0 END) as igst_5_amt,
                SUM(CASE WHEN pii.gst_percent = 5 THEN COALESCE(pii.sgst_amount, 0) ELSE 0 END) as sgst_5_amt,
                SUM(CASE WHEN pii.gst_percent = 5 THEN COALESCE(pii.cgst_amount, 0) ELSE 0 END) as cgst_5_amt,
                SUM(CASE WHEN pii.gst_percent = 18 THEN COALESCE(pii.igst_amount, 0) ELSE 0 END) as igst_18_amt,
                SUM(CASE WHEN pii.gst_percent = 18 THEN COALESCE(pii.cgst_amount, 0) ELSE 0 END) as cgst_18_amt,
                SUM(CASE WHEN pii.gst_percent = 18 THEN COALESCE(pii.sgst_amount, 0) ELSE 0 END) as sgst_18_amt,
                pi.total as total_amount
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
            'taxable_0_amount',
            'taxable_5_amount',
            'taxable_18_amount',
            'igst_5_amt',
            'sgst_5_amt',
            'cgst_5_amt',
            'igst_18_amt',
            'cgst_18_amt',
            'sgst_18_amt',
            'Total amount',
            'Inv Noble_0_amount',
            'taxable_5_amount',
            'taxable_18_amount',
            'igst_5_amt',
            'sgst_5_amt',
            'cgst_5_amt',
            'igst_18_amt',
            'cgst_18_amt',
            'sgst_18_amt',
            'Total amount',
            'Inv No',
        ];
    }

    public function map($row): array
    {
        $taxable0 = round((float) $row->taxable_0_amount, 2);
        $taxable5 = round((float) $row->taxable_5_amount, 2);
        $taxable18 = round((float) $row->taxable_18_amount, 2);
        $igst5 = round((float) $row->igst_5_amt, 2);
        $sgst5 = round((float) $row->sgst_5_amt, 2);
        $cgst5 = round((float) $row->cgst_5_amt, 2);
        $igst18 = round((float) $row->igst_18_amt, 2);
        $cgst18 = round((float) $row->cgst_18_amt, 2);
        $sgst18 = round((float) $row->sgst_18_amt, 2);
        $total = round((float) $row->total_amount, 2);

        // Format numeric strings to prevent Excel blank cell issue for 0.00
        $fmt = fn (float $v) => number_format($v, 2, '.', '');
        [$taxable0, $taxable5, $taxable18, $igst5, $sgst5, $cgst5, $igst18, $cgst18, $sgst18, $total] =
            array_map($fmt, [$taxable0, $taxable5, $taxable18, $igst5, $sgst5, $cgst5, $igst18, $cgst18, $sgst18, $total]);

        $primaryInvNo = ! empty($row->supplier_inv_no) ? $row->supplier_inv_no : $row->invoice_number;

        return [
            $primaryInvNo,
            $row->invoice_date ? Carbon::parse($row->invoice_date)->format('d-m-Y') : '',
            $row->supplier_name,
            $row->gst_no ?: '',
            $row->state_name ?: '',
            $taxable0,
            $taxable5,
            $taxable18,
            $igst5,
            $sgst5,
            $cgst5,
            $igst18,
            $cgst18,
            $sgst18,
            $total,
            $taxable0,
            $taxable5,
            $taxable18,
            $igst5,
            $sgst5,
            $cgst5,
            $igst18,
            $cgst18,
            $sgst18,
            $total,
            $primaryInvNo,
        ];
    }

    public function columnFormats(): array
    {
        $money = '0.00';

        return [
            'F' => $money, 'G' => $money, 'H' => $money,
            'I' => $money, 'J' => $money, 'K' => $money,
            'L' => $money, 'M' => $money, 'N' => $money,
            'O' => $money,
            'P' => $money, 'Q' => $money, 'R' => $money,
            'S' => $money, 'T' => $money, 'U' => $money,
            'V' => $money, 'W' => $money, 'X' => $money,
            'Y' => $money,
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
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF1E293B'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ];
                $sheet->getStyle('A1:Z1')->applyFromArray($headerStyle);
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:Z1');
                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getParent()->getActiveSheet()->setTitle('GST Purchase Detail');
            },
        ];
    }
}
