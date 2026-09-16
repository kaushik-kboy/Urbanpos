<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Indent - {{ $purchaseIndent->indent_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            color: #222;
            margin: 25px;
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
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .sub-title {
            font-size: 12px;
            color: #555;
            margin-bottom: 15px;
        }
        .border-box {
            border: 1px solid #444;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .items-table th, .items-table td {
            border: 1px solid #444;
            padding: 6px 8px;
        }
        .items-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 12px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            border: 1px solid #888;
        }
        .sign-box {
            margin-top: 60px;
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
        @media print {
            .no-print { display: none; }
            body { margin: 15px; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="padding: 6px 14px; font-weight: bold; cursor: pointer; background: #007bff; color: #fff; border: none; border-radius: 4px;">
            Print Slip
        </button>
        <button onclick="window.close()" style="padding: 6px 14px; margin-left: 5px; cursor: pointer;">
            Close
        </button>
    </div>

    <table class="header-table">
        <tr>
            <td style="vertical-align: top; width: 60%;">
                <div class="header-title">{{ $purchaseIndent->branch->name ?? 'UrbanPOS' }}</div>
                <div class="sub-title">INTERNAL PURCHASE INDENT / STORE REQUISITION SLIP</div>
                <div style="font-size: 11px; color: #555;">
                    Branch: <strong>{{ $purchaseIndent->branch->name ?? 'N/A' }}</strong> | Department: <strong>{{ $purchaseIndent->department }}</strong>
                </div>
            </td>
            <td style="vertical-align: top; text-align: right; width: 40%;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <tr>
                        <td class="text-right font-bold">Indent No:</td>
                        <td class="text-right">{{ $purchaseIndent->indent_number }}</td>
                    </tr>
                    <tr>
                        <td class="text-right font-bold">Indent Date:</td>
                        <td class="text-right">{{ $purchaseIndent->indent_date->format('d-m-Y') }}</td>
                    </tr>
                    <tr>
                        <td class="text-right font-bold">Required By:</td>
                        <td class="text-right">{{ $purchaseIndent->required_by_date ? $purchaseIndent->required_by_date->format('d-m-Y') : 'ASAP' }}</td>
                    </tr>
                    <tr>
                        <td class="text-right font-bold">Priority:</td>
                        <td class="text-right"><span class="badge">{{ $purchaseIndent->priority }}</span></td>
                    </tr>
                    <tr>
                        <td class="text-right font-bold">Status:</td>
                        <td class="text-right font-bold">{{ $purchaseIndent->status }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <hr style="border: none; border-top: 1px solid #ccc; margin: 12px 0 15px 0;">

    <table class="items-table" style="margin-bottom: 15px;">
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">#</th>
                <th style="width: 90px;">Item Code</th>
                <th>Item Description</th>
                <th style="width: 80px;" class="text-center">Current Stock</th>
                <th style="width: 80px;" class="text-right">Req. Qty</th>
                <th style="width: 80px;" class="text-right">Appr. Qty</th>
                <th style="width: 80px;" class="text-right">Est. Cost</th>
                <th style="width: 90px;" class="text-right">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseIndent->items as $idx => $line)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $line->item?->item_code ?? 'ITEM' }}</td>
                    <td>
                        {{ $line->item?->name }}
                        @if ($line->remarks)
                            <div style="font-size: 11px; color: #555; font-style: italic;">Note: {{ $line->remarks }}</div>
                        @endif
                    </td>
                    <td class="text-center">{{ number_format($line->current_stock, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($line->requested_qty, 2) }}</td>
                    <td class="text-right font-bold">
                        {{ $line->approved_qty !== null ? number_format($line->approved_qty, 2) : '—' }}
                    </td>
                    <td class="text-right">₹{{ number_format($line->estimated_cost, 2) }}</td>
                    <td class="text-right font-bold">₹{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #f9f9f9;">
                <td colspan="4" class="text-right">Totals:</td>
                <td class="text-right">{{ number_format($purchaseIndent->total_requested_qty, 2) }}</td>
                <td class="text-right">{{ $purchaseIndent->total_approved_qty > 0 ? number_format($purchaseIndent->total_approved_qty, 2) : '—' }}</td>
                <td></td>
                <td class="text-right">₹{{ number_format($purchaseIndent->total_estimated_amount, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($purchaseIndent->remarks)
        <div class="border-box" style="font-size: 12px;">
            <strong>Remarks / Reason:</strong> {{ $purchaseIndent->remarks }}
        </div>
    @endif

    <div style="font-size: 11px; color: #666; margin-top: 10px;">
        * This document is an internal store requisition. It does not alter stock ledger records or create accounts payable liabilities.
    </div>

    <div class="sign-box">
        <div class="sign-line">
            Requested By: {{ $purchaseIndent->requestedBy->name ?? 'Staff' }}<br>
            <span style="color: #777;">Store Staff / Incharge</span>
        </div>
        <div class="sign-line">
            Department Head / Verification<br>
            <span style="color: #777;">Signature & Date</span>
        </div>
        <div class="sign-line">
            Approved By: {{ $purchaseIndent->reviewedBy->name ?? 'Pending' }}<br>
            <span style="color: #777;">Store Manager / Owner</span>
        </div>
    </div>
</body>
</html>
