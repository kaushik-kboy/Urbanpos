@php
    $receiptSettings = \App\Models\ReceiptSetting::current();
    $paperSize = $receiptSettings->paper_size ?? '80mm';
    $fontSize = $receiptSettings->font_size ?? 'normal';
    $containerWidth = match($paperSize) {
        '58mm' => '58mm',
        'a4'   => '100%',
        'a5'   => '100%',
        default => '80mm',
    };
    $containerMaxWidth = match($paperSize) {
        '58mm' => '240px',
        'a4'   => '700px',
        'a5'   => '500px',
        default => '320px',
    };
    $baseFontSize = match($fontSize) {
        'small' => '10.5px',
        'large' => '13.5px',
        default => '12px',
    };
    $pageSizeRule = match($paperSize) {
        '58mm' => '58mm auto',
        'a4'   => 'A4 portrait',
        'a5'   => 'A5 portrait',
        default => '80mm auto',
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $salesBill->bill_number }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', Courier, monospace, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #eef2f5;
            color: #000;
            font-size: {{ $baseFontSize }};
            line-height: 1.35;
            padding: 20px 0;
        }

        .receipt-container {
            width: {{ $containerWidth }};
            max-width: {{ $containerMaxWidth }};
            margin: 0 auto;
            background: #fff;
            padding: 12px 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-radius: 4px;
        }

        .screen-toolbar {
            width: {{ $containerWidth }};
            max-width: {{ $containerMaxWidth }};
            margin: 0 auto 12px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }

        .btn-primary { background: #007bff; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .btn-success { background: #25d366; color: #fff; }
        .btn-primary:hover { background: #0069d9; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-success:hover { background: #1ebd56; }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .text-uppercase { text-transform: uppercase; }

        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        .double-divider {
            border-top: 1px double #000;
            border-bottom: 1px double #000;
            height: 3px;
            margin: 6px 0;
        }

        .store-title {
            font-size: 16px;
            font-weight: 900;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .store-sub {
            font-size: 11px;
            line-height: 1.25;
        }

        .meta-table, .items-table, .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td {
            padding: 1px 0;
            font-size: 11px;
            vertical-align: top;
        }

        .items-table th {
            font-size: 11px;
            padding: 4px 0;
            border-bottom: 1px dashed #000;
            text-align: left;
        }

        .items-table td {
            font-size: 11px;
            padding: 3px 0;
            vertical-align: top;
        }

        .totals-table td {
            padding: 1.5px 0;
            font-size: 11px;
        }

        .grand-total-row {
            font-size: 15px;
            font-weight: 900;
        }

        .barcode-box {
            margin: 10px auto 4px auto;
            text-align: center;
        }

        .barcode-stripes {
            display: inline-block;
            height: 35px;
            letter-spacing: 4px;
            font-size: 24px;
            font-family: 'Libre Barcode 39', 'Courier New', monospace;
            background: repeating-linear-gradient(90deg, #000 0px, #000 2px, #fff 2px, #fff 4px, #000 4px, #000 5px, #fff 5px, #fff 8px);
            width: 180px;
        }

        .barcode-text {
            font-size: 11px;
            letter-spacing: 1px;
            margin-top: 2px;
            font-weight: bold;
        }

        .eway-receipt-box {
            border: 1px dashed #000;
            padding: 5px 6px;
            margin: 6px 0;
            font-size: 10px;
            text-align: left;
            background: #fafafa;
        }

        .eway-receipt-box .title {
            font-weight: 900;
            text-align: center;
            border-bottom: 1px dashed #444;
            padding-bottom: 2px;
            margin-bottom: 3px;
            letter-spacing: 0.5px;
            font-size: 10.5px;
        }

        .footer-note {
            font-size: 10px;
            margin-top: 6px;
            line-height: 1.35;
        }

        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
            }
            .screen-toolbar {
                display: none !important;
            }
            .receipt-container {
                width: 100% !important;
                max-width: 100% !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            @page {
                size: {{ $pageSizeRule }};
                margin: 0mm 2mm;
            }
        }
        {!! $receiptSettings->custom_css ?? '' !!}
    </style>
</head>
<body>

    <div class="screen-toolbar">
        @if (!($isPublicGuest ?? false))
            <a href="{{ route('sales.sales-bills.show', $salesBill) }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back to Bill
            </a>
            <button id="btn-whatsapp-send" type="button" class="btn btn-success" onclick="sendWhatsAppInvoice()">
                <i class="fab fa-whatsapp" style="margin-right: 5px; font-size: 14px;"></i> WhatsApp
            </button>
        @else
            <span style="font-size: 11px; font-weight: bold; color: #28a745; background: #e8f5e9; padding: 5px 8px; border-radius: 4px;">
                <i class="fas fa-check-circle" style="margin-right: 4px;"></i> Verified Digital Bill
            </span>
        @endif
        <button id="btn-download-pdf" type="button" onclick="downloadReceiptPdf()" class="btn btn-primary" style="background: #17a2b8; border-color: #17a2b8;" title="Save as PDF directly to phone">
            <i class="fas fa-download" style="margin-right: 5px;"></i> Download PDF
        </button>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print" style="margin-right: 5px;"></i> Print Slip
        </button>
    </div>

    <div class="receipt-container">
        {{-- Store Logo (Optional) --}}
        @if ($receiptSettings->show_logo && $receiptSettings->logo_path)
            <div class="text-center" style="margin-bottom: 6px;">
                <img src="{{ asset($receiptSettings->logo_path) }}" alt="{{ $receiptSettings->store_name }}" style="max-width: {{ $receiptSettings->logo_width }}px; height: auto;">
            </div>
        @endif

        {{-- Store Header --}}
        <div class="text-center">
            <div class="store-title">{{ $receiptSettings->store_name ?: 'URBAN PETS' }}</div>
            @if ($receiptSettings->tagline)
                <div class="store-sub font-bold">{{ $receiptSettings->tagline }}</div>
            @endif
            <div class="store-sub font-bold">{{ $salesBill->branch?->name ?? 'Main Branch' }}</div>
            @if ($receiptSettings->header_address)
                <div class="store-sub">{!! nl2br(e($receiptSettings->header_address)) !!}</div>
            @elseif ($salesBill->branch?->address)
                <div class="store-sub">{{ $salesBill->branch->address }}</div>
            @endif
            @if ($receiptSettings->phone || $salesBill->branch?->phone)
                <div class="store-sub">Tel: {{ $receiptSettings->phone ?: $salesBill->branch?->phone }}@if ($receiptSettings->phone_alt) / {{ $receiptSettings->phone_alt }}@endif</div>
            @endif
            @if ($receiptSettings->gstin || $salesBill->branch?->gst_number)
                <div class="store-sub font-bold">GSTIN: {{ $receiptSettings->gstin ?: $salesBill->branch?->gst_number }}</div>
            @endif
        </div>

        <div class="divider"></div>

        <div class="text-center font-bold text-uppercase" style="font-size: 12px;">
            {{ $salesBill->invoice_type ?? 'TAX INVOICE' }}
        </div>

        <div class="divider"></div>

        {{-- Bill Meta --}}
        <table class="meta-table">
            <tr>
                <td class="text-left font-bold" style="width: 55%;">Bill No: {{ $salesBill->bill_number }}</td>
                <td class="text-right" style="width: 45%;">Date: {{ $salesBill->bill_date->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="text-left">Time: {{ $salesBill->bill_date ? $salesBill->bill_date->format('h:i A') : ($salesBill->created_at ? $salesBill->created_at->format('h:i A') : now()->format('h:i A')) }}</td>
                <td class="text-right">{{ $salesBill->sales_type }}</td>
            </tr>
            @if ($salesBill->customer && $salesBill->customer->name !== 'Walk-in Customer')
                <tr>
                    <td colspan="2" class="text-left">
                        Cust: <strong>{{ $salesBill->customer->name }}</strong>
                        @if ($salesBill->customer->phone)
                            ({{ $salesBill->customer->phone }})
                        @endif
                        {{-- Customer Pet Name (Dynamic Toggle) --}}
                        @if ($receiptSettings->show_customer_pet_name && $salesBill->customer->pets && $salesBill->customer->pets->count() > 0)
                            <div style="font-size: 10.5px; font-weight: bold; color: #2c3e50;">
                                🐾 Pet: {{ $salesBill->customer->pets->pluck('name')->filter()->join(', ') }}
                            </div>
                        @endif
                    </td>
                </tr>
            @endif
        </table>

        <div class="divider"></div>

        {{-- Itemized Table --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 45%;">Item Description</th>
                    <th style="width: 20%;" class="text-right">Qty</th>
                    <th style="width: 15%;" class="text-right">Rate</th>
                    <th style="width: 20%;" class="text-right">Net</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($salesBill->items as $idx => $item)
                    <tr>
                        <td colspan="4" class="font-bold" style="padding-top: 3px;">
                            {{ $idx + 1 }}. {{ $item->item?->name ?? 'Item' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="store-sub" style="color: #444;">
                            @if ($item->item?->item_code || $item->item?->ean_upc_code)
                                [{{ $item->item->item_code ?: $item->item->ean_upc_code }}]
                            @endif
                            {{-- HSN Code (Dynamic Toggle) --}}
                            @if ($receiptSettings->show_hsn_code && $item->item?->hsn_code)
                                (HSN: {{ $item->item->hsn_code }})
                            @endif
                            @if ($item->gst_percent > 0)
                                (GST {{ $item->gst_percent }}%)
                            @endif
                        </td>
                        <td class="text-right font-bold">{{ number_format($item->qty, 3) }}</td>
                        <td class="text-right">₹{{ number_format($item->sell_price, 2) }}</td>
                        <td class="text-right font-bold">₹{{ number_format($item->net_amount, 2) }}</td>
                    </tr>
                    {{-- Item Discount (Dynamic Toggle) --}}
                    @if ($receiptSettings->show_discount && $item->disc_amount > 0)
                        <tr>
                            <td colspan="4" class="text-right store-sub" style="color: #666; font-style: italic;">
                                Disc: -₹{{ number_format($item->disc_amount, 2) }} ({{ $item->disc_percent }}%)
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        {{-- Financial Summary --}}
        <table class="totals-table">
            <tr>
                <td class="text-left">Total Items / Qty:</td>
                <td class="text-right font-bold">{{ count($salesBill->items) }} / {{ number_format($salesBill->items->sum('qty'), 3) }}</td>
            </tr>
            @if ($receiptSettings->show_discount && $salesBill->disc_amount > 0)
                <tr>
                    <td class="text-left">Total Discount:</td>
                    <td class="text-right">-₹{{ number_format($salesBill->disc_amount, 2) }}</td>
                </tr>
            @endif
            @if ($receiptSettings->show_tax_breakup)
                <tr>
                    <td class="text-left">Taxable Subtotal:</td>
                    <td class="text-right">₹{{ number_format(max(0, $salesBill->total - $salesBill->total_gst - $salesBill->round_off), 2) }}</td>
                </tr>
                @if ($salesBill->total_cgst > 0 || $salesBill->total_sgst > 0)
                    <tr>
                        <td class="text-left">CGST:</td>
                        <td class="text-right">₹{{ number_format($salesBill->total_cgst, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-left">SGST:</td>
                        <td class="text-right">₹{{ number_format($salesBill->total_sgst, 2) }}</td>
                    </tr>
                @elseif ($salesBill->total_igst > 0)
                    <tr>
                        <td class="text-left">IGST:</td>
                        <td class="text-right">₹{{ number_format($salesBill->total_igst, 2) }}</td>
                    </tr>
                @elseif ($salesBill->total_gst > 0)
                    <tr>
                        <td class="text-left">GST Tax:</td>
                        <td class="text-right">₹{{ number_format($salesBill->total_gst, 2) }}</td>
                    </tr>
                @endif
            @endif
            @if ($salesBill->round_off != 0)
                <tr>
                    <td class="text-left">Round Off:</td>
                    <td class="text-right">₹{{ number_format($salesBill->round_off, 2) }}</td>
                </tr>
            @endif
        </table>

        <div class="double-divider"></div>

        <table class="totals-table">
            <tr class="grand-total-row">
                <td class="text-left font-bold">GRAND TOTAL:</td>
                <td class="text-right font-bold">₹{{ number_format($salesBill->total, 2) }}</td>
            </tr>
        </table>

        <div class="double-divider"></div>

        {{-- Payment Split --}}
        @if ($salesBill->payments && $salesBill->payments->count() > 0)
            <table class="totals-table" style="margin-bottom: 4px;">
                @foreach ($salesBill->payments as $pmt)
                    <tr>
                        <td class="text-left font-bold">Tender [{{ $pmt->tenderType?->name ?? 'Payment' }}]:</td>
                        <td class="text-right font-bold">₹{{ number_format($pmt->amount, 2) }}</td>
                    </tr>
                @endforeach
            </table>
            <div class="divider"></div>
        @elseif ($salesBill->payment_type)
            <table class="totals-table">
                <tr>
                    <td class="text-left font-bold">Paid by:</td>
                    <td class="text-right font-bold">{{ $salesBill->payment_type }}</td>
                </tr>
            </table>
            <div class="divider"></div>
        @endif

        {{-- E-Way Bill Section if available --}}
        @if ($salesBill->hasEwayBill() || $salesBill->vehicle_no)
            <div class="eway-receipt-box">
                <div class="title">*** GOVERNMENT E-WAY BILL ***</div>
                @if ($salesBill->eway_bill_no)
                    <div><strong>EWB NO:</strong> {{ $salesBill->eway_bill_no }}</div>
                @endif
                @if ($salesBill->eway_valid_until)
                    <div><strong>VALID TILL:</strong> {{ $salesBill->eway_valid_until->format('d/m/Y h:i A') }}</div>
                @endif
                @if ($salesBill->vehicle_no)
                    <div><strong>VEHICLE:</strong> {{ strtoupper($salesBill->vehicle_no) }} ({{ $salesBill->vehicle_type === 'O' ? 'ODC' : 'REG' }})</div>
                @endif
                @if ($salesBill->transporter_name)
                    <div><strong>TRANSPORTER:</strong> {{ $salesBill->transporter_name }}</div>
                @endif
                @if ($salesBill->transport_doc_no)
                    <div><strong>LR/BILTY:</strong> {{ $salesBill->transport_doc_no }}</div>
                @endif
            </div>
            <div class="divider"></div>
        @endif

        {{-- Government E-Invoice (IRN) Section if available --}}
        @if ($salesBill->hasIrn())
            <div class="eway-receipt-box" style="word-break: break-all;">
                <div class="title">*** GOVERNMENT E-INVOICE (IRN) ***</div>
                <div style="font-size: 9px; line-height: 1.2; margin-bottom: 2px;">
                    <strong>IRN:</strong> {{ $salesBill->irn }}
                </div>
                @if ($salesBill->ack_no)
                    <div><strong>ACK NO:</strong> {{ $salesBill->ack_no }}</div>
                @endif
                @if ($salesBill->ack_date)
                    <div><strong>ACK DATE:</strong> {{ $salesBill->ack_date->format('d/m/Y h:i A') }}</div>
                @endif
                <div style="text-align: center; margin-top: 4px;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data={{ urlencode($salesBill->irn) }}" alt="Govt QR" style="width: 80px; height: 80px;" />
                    <div style="font-size: 8px; color: #555;">(Govt Signed Digital IRN)</div>
                </div>
            </div>
            <div class="divider"></div>
        @endif

        {{-- Dynamic UPI Payment QR Code (Dynamic Toggle) --}}
        @if ($receiptSettings->show_upi_qr)
            <div class="text-center" style="margin: 8px auto;">
                <div style="font-weight: bold; font-size: 10.5px; margin-bottom: 3px;">
                    <i class="fas fa-qrcode" style="margin-right: 3px;"></i> SCAN TO PAY VIA UPI
                </div>
                <img src="{{ $receiptSettings->getUpiQrUrl($salesBill->total, $salesBill->bill_number) }}" alt="UPI QR Code" style="width: 115px; height: 115px; border: 1px solid #ddd; padding: 2px; background: #fff;" />
                <div style="font-size: 9.5px; font-weight: bold; margin-top: 2px; letter-spacing: 0.5px;">
                    UPI: {{ $receiptSettings->upi_id ?: '7383056626@okbizaxis' }}
                </div>
                <div style="font-size: 8.5px; color: #555;">(GPay, PhonePe, Paytm, BHIM)</div>
            </div>
            <div class="divider"></div>
        @endif

        {{-- Barcode (Dynamic Toggle) --}}
        @if ($receiptSettings->show_barcode)
            <div class="barcode-box">
                <div class="barcode-stripes"></div>
                <div class="barcode-text">* {{ $salesBill->bill_number }} *</div>
            </div>
        @endif

        {{-- Footer Note & Policy (Dynamic) --}}
        <div class="text-center footer-note">
            @if ($receiptSettings->footer_policy)
                <div style="white-space: pre-line; margin-bottom: 4px;">{!! nl2br(e($receiptSettings->footer_policy)) !!}</div>
            @endif
            @if ($receiptSettings->footer_note)
                <div class="font-bold" style="white-space: pre-line;">{!! nl2br(e($receiptSettings->footer_note)) !!}</div>
            @endif
        </div>
    </div>

    <script>
        function downloadReceiptPdf() {
            const btn = document.getElementById('btn-download-pdf');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 5px;"></i> Generating...';
            }

            const el = document.querySelector('.receipt-container');
            const pdfFormat = {!! $paperSize === 'a4' ? "'a4'" : ($paperSize === 'a5' ? "'a5'" : json_encode([$paperSize === '58mm' ? 58 : 80, 297])) !!};
            const opt = {
                margin:       [4, 4, 4, 4],
                filename:     '{{ $salesBill->bill_number }}.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: pdfFormat, orientation: 'portrait' }
            };

            html2pdf().set(opt).from(el).save().then(function () {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-download" style="margin-right: 5px;"></i> Download PDF';
                }
            }).catch(function (err) {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-download" style="margin-right: 5px;"></i> Download PDF';
                }
                alert('PDF generation notice: Opening print dialog instead.');
                window.print();
            });
        }

        // Auto trigger download or print if requested
        if (new URLSearchParams(window.location.search).get('download') === '1') {
            window.addEventListener('load', function () {
                downloadReceiptPdf();
            });
        } else if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
            window.addEventListener('load', function () {
                window.print();
            });
        }

        @if (!($isPublicGuest ?? false))
        function sendWhatsAppInvoice() {
            let phone = '{{ $salesBill->customer?->phone ?: $salesBill->customer?->mobile }}';
            if (!phone || phone.trim().length < 10) {
                phone = prompt('Customer has no mobile number saved. Enter 10-digit WhatsApp number:');
                if (!phone) return;
            }

            const btn = document.getElementById('btn-whatsapp-send');
            if (!btn) return;
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 5px;"></i> Sending...';

            fetch('{{ route('sales.sales-bills.send-whatsapp', $salesBill) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ phone: phone })
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                if (data.success) {
                    alert('✅ ' + data.message);
                } else {
                    alert('⚠️ ' + (data.error || 'Failed to send WhatsApp message'));
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                alert('Network error while dispatching WhatsApp: ' + err.message);
            });
        }
        @endif
    </script>
</body>
</html>
