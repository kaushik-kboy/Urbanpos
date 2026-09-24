@php
    $receiptSettings = \App\Models\ReceiptSetting::forDocument('sales_return', $salesReturn->branch_id ?? null);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Return - {{ $salesReturn->return_number }}</title>
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
        {!! $receiptSettings->custom_css ?? '' !!}
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right;">
        <button onclick="window.print()" class="btn-print">Print Slip</button>
    </div>

    <table class="header-table" style="margin-bottom: 12px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                @if($receiptSettings->show_logo && $receiptSettings->logo_path)
                    <img src="{{ asset($receiptSettings->logo_path) }}" alt="{{ $receiptSettings->store_name }}" style="max-width: {{ $receiptSettings->logo_width ?? 120 }}px; height: auto; margin-bottom: 6px;"><br>
                @endif
                <div class="header-title">SALES RETURN CREDIT NOTE</div>
                <div class="font-bold" style="font-size: 15px;">{{ $receiptSettings->store_name ?: ($salesReturn->branch?->name ?? config('app.name', 'UrbanPOS')) }}</div>
                @if($receiptSettings->tagline)<div style="font-size: 11px; font-weight: bold; color: #555;">{{ $receiptSettings->tagline }}</div>@endif
                <div>{!! nl2br(e($receiptSettings->header_address ?: $salesReturn->branch?->address)) !!}</div>
                @if($receiptSettings->phone || $salesReturn->branch?->phone)<div>Phone: {{ $receiptSettings->phone ?: $salesReturn->branch?->phone }}</div>@endif
                @if($receiptSettings->gstin || $salesReturn->branch?->gst_number)<div>GSTIN: <strong>{{ $receiptSettings->gstin ?: $salesReturn->branch?->gst_number }}</strong></div>@endif
            </td>
            <td style="width: 40%; vertical-align: top; text-align: right;">
                <div style="font-size: 14px; font-weight: bold;">Return #: {{ $salesReturn->return_number }}</div>
                <div>Date: <strong>{{ optional($salesReturn->return_date)->format('d-m-Y') }}</strong></div>
                @if($salesReturn->salesBill)<div>Original Bill: <strong>{{ $salesReturn->salesBill->bill_number }}</strong></div>@endif
                <div>Mode: {{ $salesReturn->return_mode }}</div>
                <div>Branch: <strong>{{ $salesReturn->branch?->name }}</strong></div>
            </td>
        </tr>
    </table>

    <div class="border-box">
        <table class="meta-table">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <strong>Customer:</strong><br>
                    <span class="font-bold">{{ $salesReturn->customer?->name ?? 'Walking Customer' }}</span><br>
                    @if($salesReturn->customer?->phone)Phone: {{ $salesReturn->customer->phone }}<br>@endif
                    {{ $salesReturn->customer?->address ?? '' }}
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <strong>Refund / Credit Mode:</strong> {{ $salesReturn->return_mode }}<br>
                    <strong>Total Amount Refunded:</strong> ₹{{ number_format((float)$salesReturn->total, 2) }}<br>
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
                <th class="text-right" style="width: 70px;">Rate</th>
                <th class="text-right" style="width: 60px;">Disc</th>
                <th class="text-right" style="width: 45px;">GST%</th>
                <th class="text-right" style="width: 65px;">GST Tax</th>
                <th class="text-right" style="width: 80px;">Net Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($salesReturn->items as $idx => $line)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $line->item?->item_code ?? $line->item?->ean_upc_code ?? '—' }}</td>
                    <td class="font-bold">{{ $line->item?->name }}</td>
                    <td class="text-center">{{ optional($line->exp_date)->format('d-m-Y') ?: '—' }}</td>
                    <td class="text-right font-bold">{{ number_format($line->qty, 3) }}</td>
                    <td class="text-right">₹{{ number_format($line->sell_price, 2) }}</td>
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
                <td class="text-right">{{ number_format($salesReturn->items->sum('qty'), 3) }}</td>
                <td colspan="3"></td>
                <td class="text-right">₹{{ number_format($salesReturn->total_gst, 2) }}</td>
                <td class="text-right">₹{{ number_format($salesReturn->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if($salesReturn->remarks)
        <div style="margin-top: 15px; border: 1px dashed #666; padding: 8px; font-size: 11px;">
            <strong>Remarks:</strong> {{ $salesReturn->remarks }}
        </div>
    @endif

    @if($receiptSettings->footer_policy || $receiptSettings->footer_note)
        <div style="margin-top: 15px; border-top: 1px dashed #777; padding-top: 8px; font-size: 11px;">
            @if($receiptSettings->footer_policy)
                <div style="white-space: pre-line; margin-bottom: 4px;">{!! nl2br(e($receiptSettings->footer_policy)) !!}</div>
            @endif
            @if($receiptSettings->footer_note)
                <div style="font-weight: bold; white-space: pre-line;">{!! nl2br(e($receiptSettings->footer_note)) !!}</div>
            @endif
        </div>
    @endif

    <div class="sign-box">
        <div class="sign-line">Customer Signature</div>
        <div class="sign-line">Authorized Signatory</div>
    </div>
</body>
</html>
