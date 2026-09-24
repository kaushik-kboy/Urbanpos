<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Invoice - {{ $purchaseInvoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #222;
            margin: 20px;
            line-height: 1.4;
        }
        .header-table, .meta-table, .items-table, .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .border-box {
            border: 1px solid #333;
            padding: 8px 10px;
            margin-bottom: 12px;
        }
        .items-table th, .items-table td {
            border: 1px solid #333;
            padding: 5px 6px;
        }
        .items-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .sign-box {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .sign-line {
            width: 200px;
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 5px;
            font-size: 11px;
        }
        .btn-print {
            padding: 7px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin-bottom: 15px;
        }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
        {!! $receiptSettings->custom_css ?? '' !!}
    </style>
</head>
@php
    $receiptSettings = \App\Models\ReceiptSetting::forDocument('purchase_invoice');
@endphp
<body>
    <div class="no-print" style="text-align: right;">
        <button onclick="window.print()" class="btn-print">Print Invoice</button>
    </div>

    <table class="header-table" style="margin-bottom: 12px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                @if($receiptSettings->show_logo && $receiptSettings->logo_path)
                    <img src="{{ asset($receiptSettings->logo_path) }}" alt="{{ $receiptSettings->store_name }}" style="max-width: {{ $receiptSettings->logo_width ?? 120 }}px; height: auto; margin-bottom: 6px;"><br>
                @endif
                <div class="header-title">PURCHASE INVOICE</div>
                <div class="font-bold" style="font-size: 14px;">{{ $receiptSettings->store_name ?: ($purchaseInvoice->branch?->name ?? config('app.name', 'UrbanPOS')) }}</div>
                @if($receiptSettings->tagline)
                    <div style="font-size: 11px; color: #555; font-weight: bold;">{{ $receiptSettings->tagline }}</div>
                @endif
                <div>{!! nl2br(e($receiptSettings->header_address ?: ($purchaseInvoice->branch?->address ?? ''))) !!}</div>
                @if($receiptSettings->phone || $purchaseInvoice->branch?->phone)
                    <div>Phone: {{ $receiptSettings->phone ?: $purchaseInvoice->branch?->phone }}</div>
                @endif
                @if($receiptSettings->gstin || $purchaseInvoice->branch?->gst_number)
                    <div>GSTIN: <strong>{{ $receiptSettings->gstin ?: $purchaseInvoice->branch?->gst_number }}</strong></div>
                @endif
            </td>
            <td style="width: 40%; vertical-align: top; text-align: right;">
                <div style="font-size: 14px; font-weight: bold;">Invoice #: {{ $purchaseInvoice->invoice_number }}</div>
                <div>Date: <strong>{{ optional($purchaseInvoice->invoice_date)->format('d-m-Y') }}</strong></div>
                @if($purchaseInvoice->supplier_inv_no)<div>Supplier Inv: {{ $purchaseInvoice->supplier_inv_no }}</div>@endif
                @if($purchaseInvoice->supplier_inv_date)<div>Supplier Inv Date: {{ $purchaseInvoice->supplier_inv_date->format('d-m-Y') }}</div>@endif
                <div>Type: {{ $purchaseInvoice->purchase_type ?? 'Local' }}</div>
            </td>
        </tr>
    </table>

    <div class="border-box">
        <table class="meta-table">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <strong>Supplier:</strong><br>
                    <span class="font-bold">{{ $purchaseInvoice->supplier?->name }}</span><br>
                    {{ $purchaseInvoice->supplier?->address ?? '' }}<br>
                    @if($purchaseInvoice->supplier?->phone)Phone: {{ $purchaseInvoice->supplier->phone }}<br>@endif
                    @if($purchaseInvoice->supplier?->gst_number)GSTIN: <strong>{{ $purchaseInvoice->supplier->gst_number }}</strong><br>@endif
                </td>
                <td style="width: 50%; vertical-align: top;">
                    @if($purchaseInvoice->purchaseOrder)
                        <strong>PO Number:</strong> {{ $purchaseInvoice->purchaseOrder->po_number }}<br>
                    @endif
                    @if($purchaseInvoice->grn_number)
                        <strong>GRN Number:</strong> {{ $purchaseInvoice->grn_number }}<br>
                    @endif
                    @if($purchaseInvoice->supplier_inv_amount > 0)
                        <strong>Supplier Amount:</strong> ₹{{ number_format((float)$purchaseInvoice->supplier_inv_amount, 2) }}<br>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 30px;">#</th>
                <th class="text-left" style="width: 90px;">Code</th>
                <th class="text-left">Item Description</th>
                <th class="text-center" style="width: 75px;">Exp Date</th>
                <th class="text-right" style="width: 50px;">Qty</th>
                <th class="text-right" style="width: 45px;">Free</th>
                <th class="text-right" style="width: 70px;">Cost</th>
                <th class="text-right" style="width: 60px;">Disc</th>
                <th class="text-right" style="width: 45px;">GST%</th>
                <th class="text-right" style="width: 65px;">GST Tax</th>
                <th class="text-right" style="width: 80px;">Net Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseInvoice->items as $idx => $line)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $line->item?->item_code ?? $line->item?->ean_upc_code ?? '—' }}</td>
                    <td class="font-bold">{{ $line->item?->name }}</td>
                    <td class="text-center">{{ optional($line->exp_date)->format('d-m-Y') ?: '—' }}</td>
                    <td class="text-right font-bold">{{ number_format($line->qty, 3) }}</td>
                    <td class="text-right">{{ $line->free_qty > 0 ? number_format($line->free_qty, 3) : '—' }}</td>
                    <td class="text-right">₹{{ number_format($line->cost_price, 2) }}</td>
                    <td class="text-right">{{ $line->disc_amount > 0 ? '₹' . number_format($line->disc_amount, 2) : '—' }}</td>
                    <td class="text-right">{{ number_format($line->gst_percent, 1) }}%</td>
                    <td class="text-right">₹{{ number_format($line->gst_tax_amount, 2) }}</td>
                    <td class="text-right font-bold">₹{{ number_format($line->net_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f9f9f9; font-weight: bold;">
                <td colspan="4" class="text-right">Totals:</td>
                <td class="text-right">{{ number_format($purchaseInvoice->items->sum('qty') + $purchaseInvoice->items->sum('free_qty'), 3) }}</td>
                <td></td>
                <td></td>
                <td class="text-right">₹{{ number_format($purchaseInvoice->items->sum('disc_amount'), 2) }}</td>
                <td></td>
                <td class="text-right">₹{{ number_format($purchaseInvoice->total_gst, 2) }}</td>
                <td class="text-right">₹{{ number_format($purchaseInvoice->items->sum('net_amount'), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <table style="width: 100%; margin-top: 15px;">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                @if($purchaseInvoice->remarks)
                    <div style="border: 1px dashed #666; padding: 8px; font-size: 11px;">
                        <strong>Remarks:</strong> {{ $purchaseInvoice->remarks }}
                    </div>
                @endif
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td class="text-right" style="padding: 3px 6px;">Items Total (Net):</td>
                        <td class="text-right font-bold" style="padding: 3px 6px; width: 100px;">₹{{ number_format($purchaseInvoice->items->sum('net_amount'), 2) }}</td>
                    </tr>
                    @if($purchaseInvoice->freight > 0)
                        <tr>
                            <td class="text-right" style="padding: 3px 6px;">Freight:</td>
                            <td class="text-right" style="padding: 3px 6px;">₹{{ number_format($purchaseInvoice->freight, 2) }}</td>
                        </tr>
                    @endif
                    @if($purchaseInvoice->scheme_item_disc_amt > 0)
                        <tr>
                            <td class="text-right" style="padding: 3px 6px;">Scheme Discount:</td>
                            <td class="text-right" style="padding: 3px 6px;">-₹{{ number_format($purchaseInvoice->scheme_item_disc_amt, 2) }}</td>
                        </tr>
                    @endif
                    @if($purchaseInvoice->other_disc_amt > 0)
                        <tr>
                            <td class="text-right" style="padding: 3px 6px;">Other Discount:</td>
                            <td class="text-right" style="padding: 3px 6px;">-₹{{ number_format($purchaseInvoice->other_disc_amt, 2) }}</td>
                        </tr>
                    @endif
                    @if($purchaseInvoice->round_off != 0)
                        <tr>
                            <td class="text-right" style="padding: 3px 6px;">Round Off:</td>
                            <td class="text-right" style="padding: 3px 6px;">₹{{ number_format($purchaseInvoice->round_off, 2) }}</td>
                        </tr>
                    @endif
                    <tr style="border-top: 2px solid #333; font-size: 14px;">
                        <td class="text-right font-bold" style="padding: 5px 6px;">Final Amount:</td>
                        <td class="text-right font-bold" style="padding: 5px 6px;">₹{{ number_format($purchaseInvoice->total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="sign-box">
        <div class="sign-line">Prepared / Received By</div>
        <div class="sign-line">Authorized Signatory</div>
    </div>

    @if($receiptSettings->footer_policy || $receiptSettings->footer_note)
        <div style="margin-top: 25px; font-size: 11px; color: #444; border-top: 1px dashed #999; padding-top: 10px; text-align: center;">
            @if($receiptSettings->footer_policy)
                <div style="white-space: pre-line; margin-bottom: 4px;">{!! nl2br(e($receiptSettings->footer_policy)) !!}</div>
            @endif
            @if($receiptSettings->footer_note)
                <div style="font-weight: bold; white-space: pre-line;">{!! nl2br(e($receiptSettings->footer_note)) !!}</div>
            @endif
        </div>
    @endif

    @if($receiptSettings->show_barcode)
        <div style="text-align: center; margin-top: 20px;">
            <div style="display: inline-block; height: 32px; width: 180px; background: repeating-linear-gradient(90deg, #000 0px, #000 2px, #fff 2px, #fff 4px, #000 4px, #000 5px, #fff 5px, #fff 8px);"></div>
            <div style="font-size: 10px; font-weight: bold; letter-spacing: 1px; margin-top: 2px;">* {{ $purchaseInvoice->invoice_number }} *</div>
        </div>
    @endif
</body>
</html>
