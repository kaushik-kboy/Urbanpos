<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Return - {{ $purchaseReturn->return_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #222;
            margin: 20px;
            line-height: 1.4;
        }
        .header-table, .meta-table, .items-table {
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
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right;">
        <button onclick="window.print()" class="btn-print">Print Return Note</button>
    </div>

    <table class="header-table" style="margin-bottom: 12px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="header-title">PURCHASE DEBIT NOTE / RETURN</div>
                <div class="font-bold" style="font-size: 14px;">{{ $purchaseReturn->branch?->name ?? config('app.name', 'UrbanPOS') }}</div>
                <div>{{ $purchaseReturn->branch?->address ?? '' }}</div>
                @if($purchaseReturn->branch?->phone)<div>Phone: {{ $purchaseReturn->branch->phone }}</div>@endif
                @if($purchaseReturn->branch?->gst_number)<div>GSTIN: <strong>{{ $purchaseReturn->branch->gst_number }}</strong></div>@endif
            </td>
            <td style="width: 40%; vertical-align: top; text-align: right;">
                <div style="font-size: 14px; font-weight: bold;">Return #: {{ $purchaseReturn->return_number }}</div>
                <div>Date: <strong>{{ optional($purchaseReturn->return_date)->format('d-m-Y') }}</strong></div>
                @if($purchaseReturn->purchaseInvoice)<div>Orig Inv: <strong>{{ $purchaseReturn->purchaseInvoice->invoice_number }}</strong></div>@endif
                @if($purchaseReturn->supplier_debit_note_no)<div>Debit Note #: {{ $purchaseReturn->supplier_debit_note_no }}</div>@endif
                <div>Type: {{ $purchaseReturn->purchase_type ?? 'Local' }}</div>
            </td>
        </tr>
    </table>

    <div class="border-box">
        <table class="meta-table">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <strong>Return To (Supplier):</strong><br>
                    <span class="font-bold">{{ $purchaseReturn->supplier?->name }}</span><br>
                    {{ $purchaseReturn->supplier?->address ?? '' }}<br>
                    @if($purchaseReturn->supplier?->phone)Phone: {{ $purchaseReturn->supplier->phone }}<br>@endif
                    @if($purchaseReturn->supplier?->gst_number)GSTIN: <strong>{{ $purchaseReturn->supplier->gst_number }}</strong><br>@endif
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <strong>Branch:</strong> {{ $purchaseReturn->branch?->name }}<br>
                    <strong>Status:</strong> {{ $purchaseReturn->status }}<br>
                    <strong>Total Value:</strong> ₹{{ number_format((float)$purchaseReturn->total, 2) }}<br>
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
                <th class="text-right" style="width: 70px;">Cost Price</th>
                <th class="text-right" style="width: 60px;">Disc</th>
                <th class="text-right" style="width: 45px;">GST%</th>
                <th class="text-right" style="width: 65px;">GST Tax</th>
                <th class="text-right" style="width: 80px;">Net Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseReturn->items as $idx => $line)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $line->item?->item_code ?? $line->item?->ean_upc_code ?? '—' }}</td>
                    <td class="font-bold">{{ $line->item?->name }}</td>
                    <td class="text-center">{{ optional($line->exp_date)->format('d-m-Y') ?: '—' }}</td>
                    <td class="text-right font-bold">{{ number_format($line->qty, 3) }}</td>
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
                <td class="text-right">{{ number_format($purchaseReturn->items->sum('qty'), 3) }}</td>
                <td colspan="3"></td>
                <td class="text-right">₹{{ number_format($purchaseReturn->total_gst, 2) }}</td>
                <td class="text-right">₹{{ number_format($purchaseReturn->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if($purchaseReturn->remarks)
        <div style="margin-top: 15px; border: 1px dashed #666; padding: 8px; font-size: 11px;">
            <strong>Remarks:</strong> {{ $purchaseReturn->remarks }}
        </div>
    @endif

    <div class="sign-box">
        <div class="sign-line">Returned By</div>
        <div class="sign-line">Supplier Acknowledgment</div>
    </div>
</body>
</html>
