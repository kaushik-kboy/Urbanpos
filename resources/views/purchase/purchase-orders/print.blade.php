<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - {{ $purchaseOrder->po_number }}</title>
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
            background: #fafafa;
        }
        .items-table th, .items-table td {
            border: 1px solid #333;
            padding: 6px 8px;
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
            background: #0f172a;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin-right: 8px;
        }
        .btn-toggle {
            padding: 6px 14px;
            background: #ffffff;
            color: #0f172a;
            border: 1.5px solid #0f172a;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
        }
        .btn-toggle.active {
            background: #0f172a;
            color: #ffffff;
        }
        .no-print-toolbar {
            background: #e2e8f0;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        @media print {
            .no-print, .no-print-toolbar { display: none !important; }
            body { margin: 0; padding: 10px; }
        }
    </style>
</head>
<body>

    @php
        $isSupplierFormat = ($format ?? 'supplier') === 'supplier';
    @endphp

    <div class="no-print-toolbar">
        <div>
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Purchase Order
            </button>
        </div>
        <div>
            <span style="font-weight: bold; margin-right: 8px; font-size: 11px; text-transform: uppercase; color: #475569;">Format:</span>
            <a href="?format=supplier" class="btn-toggle {{ $isSupplierFormat ? 'active' : '' }}">
                Supplier Order Copy (Code, Name, MRP, Qty)
            </a>
            <a href="?format=internal" class="btn-toggle {{ ! $isSupplierFormat ? 'active' : '' }}" style="margin-left: 6px;">
                Internal Office Copy (With Costs & GST)
            </a>
        </div>
    </div>

    {{-- Header --}}
    <table class="header-table" style="margin-bottom: 12px;">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="header-title">
                    {{ $isSupplierFormat ? 'PURCHASE ORDER' : 'PURCHASE ORDER (OFFICE COPY)' }}
                </div>
                <div style="font-size: 14px; font-weight: bold;">{{ $purchaseOrder->branch?->name ?? config('app.name', 'UrbanPOS') }}</div>
                @if($purchaseOrder->branch?->address)
                    <div>{{ $purchaseOrder->branch->address }}</div>
                @endif
                @if($purchaseOrder->branch?->phone)
                    <div>Phone: {{ $purchaseOrder->branch->phone }}</div>
                @endif
                @if($purchaseOrder->branch?->gst_no)
                    <div>GSTIN: <strong>{{ $purchaseOrder->branch->gst_no }}</strong></div>
                @endif
            </td>
            <td style="width: 45%; vertical-align: top; text-align: right;">
                <table style="margin-left: auto; text-align: left;">
                    <tr>
                        <td class="font-bold" style="padding-right: 10px;">PO Number:</td>
                        <td style="font-size: 14px;"><strong>{{ $purchaseOrder->po_number }}</strong></td>
                    </tr>
                    <tr>
                        <td class="font-bold" style="padding-right: 10px;">PO Date:</td>
                        <td>{{ optional($purchaseOrder->po_date)->format('d-m-Y') }}</td>
                    </tr>
                    <tr>
                        <td class="font-bold" style="padding-right: 10px;">Status:</td>
                        <td><span style="background: #e2e8f0; padding: 2px 6px; border-radius: 3px; font-weight: bold;">{{ $purchaseOrder->status ?? 'Active' }}</span></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Supplier & Delivery Box --}}
    <div class="border-box">
        <table class="meta-table">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 15px;">
                    <div class="font-bold" style="text-decoration: underline; margin-bottom: 4px; color: #0f172a;">SUPPLIER / VENDOR DETAILS:</div>
                    <div style="font-size: 13px; font-weight: bold;">{{ $purchaseOrder->supplier?->name }}</div>
                    @if($purchaseOrder->supplier?->address)
                        <div>{{ $purchaseOrder->supplier->address }}</div>
                    @endif
                    @if($purchaseOrder->supplier?->phone || $purchaseOrder->supplier?->mobile)
                        <div>Mobile / Phone: <strong>{{ $purchaseOrder->supplier->phone ?: $purchaseOrder->supplier->mobile }}</strong></div>
                    @endif
                    @if($purchaseOrder->supplier?->gst_no)
                        <div>GSTIN: <strong>{{ $purchaseOrder->supplier->gst_no }}</strong></div>
                    @endif
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 15px; border-left: 1px dashed #cbd5e1;">
                    <div class="font-bold" style="text-decoration: underline; margin-bottom: 4px; color: #0f172a;">DELIVERY LOCATION / SHIP TO:</div>
                    <div style="font-size: 13px; font-weight: bold;">{{ $purchaseOrder->branch?->name }}</div>
                    @if($purchaseOrder->branch?->address)
                        <div>{{ $purchaseOrder->branch->address }}</div>
                    @endif
                    @if($purchaseOrder->branch?->phone)
                        <div>Contact: {{ $purchaseOrder->branch->phone }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Items Table --}}
    @if($isSupplierFormat)
        {{-- SUPPLIER-FACING FORMAT: Item Code, Item Name, MRP, Order Qty --}}
        <table class="items-table" style="margin-bottom: 12px;">
            <thead>
                <tr>
                    <th class="text-center" style="width: 40px;">#</th>
                    <th class="text-left" style="width: 140px;">Item Code / Barcode</th>
                    <th class="text-left">Item Name / Description</th>
                    <th class="text-right" style="width: 110px;">MRP (₹)</th>
                    <th class="text-right" style="width: 90px;">Order Qty</th>
                    @if($purchaseOrder->items->sum('free_qty') > 0)
                        <th class="text-right" style="width: 70px;">Free Qty</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($purchaseOrder->items as $i => $row)
                    @php
                        $mrpVal = (float) ($row->mrp > 0 ? $row->mrp : ($row->item?->mrp ?? 0));
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td class="font-bold">{{ $row->item?->item_code ?: ($row->item?->ean_upc_code ?: '-') }}</td>
                        <td>{{ $row->item?->name }}</td>
                        <td class="text-right font-bold">{{ $mrpVal > 0 ? '₹' . number_format($mrpVal, 2) : '—' }}</td>
                        <td class="text-right font-bold" style="font-size: 13px;">{{ number_format($row->qty, 0) }}</td>
                        @if($purchaseOrder->items->sum('free_qty') > 0)
                            <td class="text-right">{{ $row->free_qty > 0 ? number_format($row->free_qty, 0) : '-' }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-bold" style="background-color: #f1f5f9; font-size: 13px;">
                    <td colspan="3" class="text-right">Total Order Summary:</td>
                    <td class="text-right">{{ $purchaseOrder->items->count() }} items</td>
                    <td class="text-right font-bold" style="font-size: 14px;">{{ number_format($purchaseOrder->items->sum('qty'), 0) }} units</td>
                    @if($purchaseOrder->items->sum('free_qty') > 0)
                        <td class="text-right">{{ number_format($purchaseOrder->items->sum('free_qty'), 0) }}</td>
                    @endif
                </tr>
            </tfoot>
        </table>
    @else
        {{-- INTERNAL OFFICE VALUATION COPY: Detailed Rates, Tax, Net --}}
        <table class="items-table" style="margin-bottom: 12px;">
            <thead>
                <tr>
                    <th class="text-center" style="width: 30px;">#</th>
                    <th class="text-left">Item Description</th>
                    <th class="text-left" style="width: 90px;">Item Code</th>
                    <th class="text-right" style="width: 75px;">MRP</th>
                    <th class="text-right" style="width: 55px;">Qty</th>
                    <th class="text-right" style="width: 45px;">Free</th>
                    <th class="text-right" style="width: 70px;">Cost</th>
                    <th class="text-right" style="width: 60px;">Disc</th>
                    <th class="text-right" style="width: 50px;">GST %</th>
                    <th class="text-right" style="width: 65px;">GST Amt</th>
                    <th class="text-right" style="width: 80px;">Net Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchaseOrder->items as $i => $row)
                    @php
                        $mrpVal = (float) ($row->mrp > 0 ? $row->mrp : ($row->item?->mrp ?? 0));
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $row->item?->name }}</td>
                        <td>{{ $row->item?->item_code ?? $row->item?->ean_upc_code ?? '-' }}</td>
                        <td class="text-right">{{ $mrpVal > 0 ? number_format($mrpVal, 2) : '-' }}</td>
                        <td class="text-right">{{ number_format($row->qty, 0) }}</td>
                        <td class="text-right">{{ $row->free_qty > 0 ? number_format($row->free_qty, 0) : '-' }}</td>
                        <td class="text-right">{{ number_format($row->cost_price, 2) }}</td>
                        <td class="text-right">{{ $row->disc_amount > 0 ? number_format($row->disc_amount, 2) : '-' }}</td>
                        <td class="text-right">{{ number_format($row->gst_percent, 2) }}%</td>
                        <td class="text-right">{{ number_format($row->gst_tax_amount, 2) }}</td>
                        <td class="text-right font-bold">{{ number_format($row->net_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-bold" style="background-color: #f9f9f9;">
                    <td colspan="4" class="text-right">Totals:</td>
                    <td class="text-right">{{ number_format($purchaseOrder->items->sum('qty'), 0) }}</td>
                    <td class="text-right">{{ number_format($purchaseOrder->items->sum('free_qty'), 0) }}</td>
                    <td colspan="3"></td>
                    <td class="text-right">₹{{ number_format($purchaseOrder->total_gst, 2) }}</td>
                    <td class="text-right">₹{{ number_format($purchaseOrder->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <table class="footer-table" style="margin-bottom: 20px;">
            <tr>
                <td style="width: 60%; vertical-align: top;">
                    @if($purchaseOrder->remarks)
                        <div style="font-size: 11px;"><strong>Remarks:</strong> {{ $purchaseOrder->remarks }}</div>
                    @endif
                    @if($purchaseOrder->message)
                        <div style="font-size: 11px;"><strong>Message:</strong> {{ $purchaseOrder->message }}</div>
                    @endif
                </td>
                <td style="width: 40%; vertical-align: top;">
                    <table style="width: 100%; border-collapse: collapse;">
                        @if($purchaseOrder->scheme_item_disc_amt > 0)
                            <tr>
                                <td style="padding: 3px 0;">Scheme Discount:</td>
                                <td class="text-right" style="padding: 3px 0;">-₹{{ number_format((float)$purchaseOrder->scheme_item_disc_amt, 2) }}</td>
                            </tr>
                        @endif
                        @if($purchaseOrder->other_disc_amt > 0)
                            <tr>
                                <td style="padding: 3px 0;">Other Discount:</td>
                                <td class="text-right" style="padding: 3px 0;">-₹{{ number_format((float)$purchaseOrder->other_disc_amt, 2) }}</td>
                            </tr>
                        @endif
                        @if($purchaseOrder->freight > 0)
                            <tr>
                                <td style="padding: 3px 0;">Freight:</td>
                                <td class="text-right" style="padding: 3px 0;">₹{{ number_format((float)$purchaseOrder->freight, 2) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td style="padding: 3px 0;">Total Tax (GST):</td>
                            <td class="text-right" style="padding: 3px 0;">₹{{ number_format((float)($purchaseOrder->total_gst ?? 0), 2) }}</td>
                        </tr>
                        <tr style="border-top: 1px solid #333; font-size: 14px;" class="font-bold">
                            <td style="padding: 5px 0;">Grand Total:</td>
                            <td class="text-right" style="padding: 5px 0;">₹{{ number_format((float)($purchaseOrder->total ?? 0), 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @endif

    @if($isSupplierFormat && ($purchaseOrder->message || $purchaseOrder->remarks))
        <div style="margin-top: 15px; padding: 8px 12px; border: 1px dashed #64748b; background: #f8fafc; border-radius: 4px;">
            @if($purchaseOrder->message)
                <div><strong>Note to Supplier:</strong> {{ $purchaseOrder->message }}</div>
            @endif
            @if($purchaseOrder->remarks)
                <div style="margin-top: 4px;"><strong>Remarks:</strong> {{ $purchaseOrder->remarks }}</div>
            @endif
        </div>
    @endif

    <div class="sign-box">
        <div class="sign-line">Prepared By</div>
        <div class="sign-line">Verified By</div>
        <div class="sign-line">Authorized Signatory</div>
    </div>

</body>
</html>
