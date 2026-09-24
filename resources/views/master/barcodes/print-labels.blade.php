<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Barcode Stickers - {{ $storeName }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

    <style>
        body {
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #0f172a;
        }

        /* Toolbar (Hidden on Print) */
        .print-toolbar {
            background: #ffffff;
            border-bottom: 1px solid #cbd5e1;
            padding: 10px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.06);
        }

        .labels-preview-wrap {
            padding: 16px 0;
        }

        /* ── 102x63.5mm Label Style (TSC TE244 4" x 2.5" Thermal Roll) ──────── */
        .format-102x64 .barcode-label-card {
            width: 102mm;
            height: 63.5mm;
            padding: 3mm 4mm;
            box-sizing: border-box;
            border: 1px dashed #94a3b8;
            margin: 4mm auto;
            background: #ffffff;
            page-break-inside: avoid;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            text-align: center;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .format-102x64 .label-store-name {
            font-size: 11pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #000000;
            border-bottom: 1.5px solid #000000;
            padding-bottom: 1mm;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .format-102x64 .label-item-name {
            font-size: 11pt;
            font-weight: 700;
            line-height: 1.25;
            max-height: 2.5em;
            margin: 1mm 0 0.5mm 0;
            color: #000000;
            text-transform: uppercase;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .format-102x64 .label-item-meta {
            font-size: 8pt;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5mm;
        }
        .format-102x64 .label-barcode-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0.5mm 0;
        }
        .format-102x64 .label-barcode-svg {
            max-width: 96%;
            max-height: 26mm;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .format-102x64 .label-prices {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10.5pt;
            font-weight: 700;
            line-height: 1.2;
            border-top: 1.5px solid #000000;
            padding-top: 1.2mm;
            margin-top: 0.5mm;
        }
        .format-102x64 .label-mrp {
            font-size: 9.5pt;
            text-decoration: line-through;
            color: #475569;
        }
        .format-102x64 .label-sell {
            font-size: 13.5pt;
            font-weight: 900;
            color: #000000;
        }

        /* ── 50x25mm Label Style (Standard 1-Up Roll) ───────────────────────── */
        .format-50x25 .barcode-label-card {
            width: 50mm;
            height: 25mm;
            padding: 1.5mm 2mm;
            box-sizing: border-box;
            border: 1px dashed #cbd5e1;
            margin: 2mm auto;
            background: #ffffff;
            page-break-inside: avoid;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            text-align: center;
        }
        .format-50x25 .label-store-name {
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .format-50x25 .label-item-name {
            font-size: 7.5pt;
            font-weight: 700;
            line-height: 1.1;
            max-height: 2.2em;
            overflow: hidden;
            margin: 0.5mm 0;
        }
        .format-50x25 .label-barcode-svg {
            max-width: 98%;
            max-height: 10mm;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .format-50x25 .label-prices {
            display: flex;
            justify-content: space-around;
            align-items: center;
            font-size: 7pt;
            font-weight: 700;
            line-height: 1;
            border-top: 0.5px solid #000;
            padding-top: 0.5mm;
        }

        /* ── 38x25mm Label Style (Compact 2-Up Roll) ─────────────────────────── */
        .format-38x25 .labels-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2mm;
        }
        .format-38x25 .barcode-label-card {
            width: 38mm;
            height: 25mm;
            padding: 1mm 1.5mm;
            box-sizing: border-box;
            border: 1px dashed #cbd5e1;
            background: #ffffff;
            page-break-inside: avoid;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            text-align: center;
        }
        .format-38x25 .label-store-name {
            font-size: 6.5pt;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
        }
        .format-38x25 .label-item-name {
            font-size: 6.5pt;
            font-weight: 600;
            line-height: 1.1;
            max-height: 2em;
            overflow: hidden;
        }
        .format-38x25 .label-barcode-svg {
            max-width: 98%;
            max-height: 9mm;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .format-38x25 .label-prices {
            display: flex;
            justify-content: space-around;
            align-items: center;
            font-size: 6.5pt;
            font-weight: 700;
            line-height: 1;
            border-top: 0.5px solid #000;
            padding-top: 0.5mm;
        }

        /* ── A4 Sheet Matrix (4 Columns x 10 Rows) ─────────────────────────── */
        .format-a4 .labels-container {
            width: 210mm;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2mm;
            padding: 5mm;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .format-a4 .barcode-label-card {
            height: 27mm;
            padding: 1.5mm;
            border: 1px dashed #e2e8f0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
            overflow: hidden;
        }
        .format-a4 .label-store-name {
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
        }
        .format-a4 .label-item-name {
            font-size: 7.5pt;
            font-weight: 600;
            line-height: 1.1;
            max-height: 2.2em;
            overflow: hidden;
        }
        .format-a4 .label-barcode-svg {
            max-width: 98%;
            max-height: 9mm;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .format-a4 .label-prices {
            display: flex;
            justify-content: space-around;
            align-items: center;
            font-size: 7pt;
            font-weight: 700;
            line-height: 1;
            border-top: 0.5px solid #000;
            padding-top: 0.5mm;
        }

        /* Common Elements */
        .label-mrp {
            text-decoration: line-through;
            font-weight: 500;
            color: #475569;
        }

        /* ── Exact Print Media Queries ─────────────────────────────────────── */
        @media print {
            .print-toolbar, .print-instructions, .no-print {
                display: none !important;
            }
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: transparent !important;
                width: 100% !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .labels-preview-wrap, .labels-container {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            .barcode-label-card {
                border: none !important;
                box-shadow: none !important;
            }

            /* 102x63.5mm exact thermal roll printing */
            .format-102x64 html, .format-102x64 body {
                width: 102mm !important;
            }
            .format-102x64 .barcode-label-card {
                width: 102mm !important;
                height: 61.5mm !important; /* 61.5mm height avoids spilling over the 2mm gap sensor */
                max-height: 61.5mm !important;
                margin: 0 auto !important;
                padding: 2.5mm 4mm !important;
                box-sizing: border-box !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
            }
            .format-102x64 .barcode-label-card:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }

            /* 50x25mm exact thermal roll printing */
            .format-50x25 html, .format-50x25 body {
                width: 50mm !important;
            }
            .format-50x25 .barcode-label-card {
                width: 50mm !important;
                height: 24mm !important;
                max-height: 24mm !important;
                margin: 0 auto !important;
                padding: 1mm 2mm !important;
                box-sizing: border-box !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
            }
            .format-50x25 .barcode-label-card:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }

            /* 38x25mm */
            .format-38x25 html, .format-38x25 body {
                width: 78mm !important;
            }
            .format-38x25 .barcode-label-card {
                width: 38mm !important;
                height: 24mm !important;
                max-height: 24mm !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            /* A4 Sheet */
            .format-a4 .labels-container {
                box-shadow: none !important;
                padding: 0 !important;
                width: 100% !important;
            }
        }
    </style>

    {{-- Precise @page size rules for thermal label printers --}}
    @if($format === '102x64')
    <style>
        @page {
            size: 102mm 63.50mm;
            margin: 0mm !important;
        }
    </style>
    @elseif($format === '50x25')
    <style>
        @page {
            size: 50mm 25mm;
            margin: 0mm !important;
        }
    </style>
    @elseif($format === '38x25')
    <style>
        @page {
            size: 78mm 25mm;
            margin: 0mm !important;
        }
    </style>
    @elseif($format === 'a4')
    <style>
        @page {
            size: A4;
            margin: 5mm !important;
        }
    </style>
    @endif
</head>
<body class="format-{{ $format }}">

    <!-- Toolbar -->
    <div class="print-toolbar d-flex justify-content-between align-items-center flex-wrap">
        <div class="d-flex align-items-center my-1">
            <a href="{{ route('master.items.index') }}" class="btn btn-primary btn-sm mr-2 font-weight-bold shadow-sm">
                <i class="fas fa-boxes mr-1"></i> Items Listing
            </a>
            @if(request()->filled('purchase_invoice_id'))
                <a href="{{ route('purchase.purchase-invoices.edit', request('purchase_invoice_id')) }}" class="btn btn-outline-secondary btn-sm mr-2 font-weight-bold">
                    <i class="fas fa-edit mr-1"></i> Back to Invoice
                </a>
            @else
                <button type="button" class="btn btn-outline-secondary btn-sm mr-2 font-weight-bold" onclick="if (window.opener) { window.close(); } else { window.location.href='{{ route('master.items.index') }}'; }">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </button>
            @endif
            <h5 class="mb-0 font-weight-bold text-dark mx-2">
                <i class="fas fa-barcode text-primary mr-1"></i> Barcode Stickers
            </h5>
            <span class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size: 13px;">
                Total: {{ count($labels) }} Stickers
            </span>
        </div>

        <div class="d-flex align-items-center my-1 flex-wrap">
            <div class="btn-group btn-group-sm mr-2 my-1">
                <a href="{{ request()->fullUrlWithQuery(['format' => '102x64']) }}" class="btn {{ $format === '102x64' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-primary' }}" title="TSC TE244 102mm x 63.5mm (4x2.5 inch) Roll">
                    <i class="fas fa-tag mr-1"></i> 102x63.5 mm (TSC TE244)
                </a>
                <a href="{{ request()->fullUrlWithQuery(['format' => '50x25']) }}" class="btn {{ $format === '50x25' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-primary' }}">
                    50x25 mm (1-Up)
                </a>
                <a href="{{ request()->fullUrlWithQuery(['format' => '38x25']) }}" class="btn {{ $format === '38x25' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-primary' }}">
                    38x25 mm (2-Up)
                </a>
                <a href="{{ request()->fullUrlWithQuery(['format' => 'a4']) }}" class="btn {{ $format === 'a4' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-primary' }}">
                    A4 Sheet (40-Up)
                </a>
            </div>

            {{-- TSPL Raw Download for TSC TE244 --}}
            <a href="{{ route('master.barcodes.tspl', request()->query()) }}" class="btn btn-info btn-sm font-weight-bold mr-2 my-1 shadow-sm" title="Download native TSPL command file for TSC TE244 raw printing">
                <i class="fas fa-file-download mr-1"></i> TSPL File (.prn)
            </a>

            <button type="button" class="btn btn-success font-weight-bold shadow-sm px-4 my-1" onclick="window.print();">
                <i class="fas fa-print mr-2"></i> Print Stickers
            </button>
        </div>
    </div>

    <!-- On-screen Guide for TSC TE244 / Thermal Printers -->
    <div class="container-fluid print-instructions mt-3">
        <div class="alert alert-light border border-primary shadow-sm py-2 px-3 mb-0 d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center my-1">
                <i class="fas fa-info-circle text-primary mr-2" style="font-size: 20px;"></i>
                <span class="small font-weight-bold text-dark">
                    <strong>TSC TE244 Print Guide:</strong>
                    1. Destination = <strong>TSC TE244</strong> &nbsp;|&nbsp;
                    2. Paper Size = <strong>102 x 63.5 mm</strong> (or 4.00 x 2.50 in) &nbsp;|&nbsp;
                    3. Margins = <strong>None</strong> &nbsp;|&nbsp;
                    4. Uncheck <strong>"Headers and footers"</strong>
                </span>
            </div>
            <div class="my-1">
                <span class="badge badge-info px-2 py-1">Roll: 102.00 mm x 63.50 mm (Gap: 2.00 mm)</span>
            </div>
        </div>
    </div>

    <!-- Labels Preview Container -->
    <div class="labels-preview-wrap">
        @if(empty($labels))
            <div class="text-center py-5">
                <i class="fas fa-barcode text-muted" style="font-size: 48px;"></i>
                <h4 class="mt-3 font-weight-bold text-secondary">No Items Selected for Label Printing</h4>
                <p class="text-muted">Select items from Item Master or open a Purchase Invoice to print labels.</p>
                <a href="{{ route('master.items.index') }}" class="btn btn-primary font-weight-bold mt-2">
                    <i class="fas fa-boxes mr-1"></i> Go to Items Listing
                </a>
            </div>
        @else
            <div class="labels-container">
                @foreach ($labels as $lbl)
                    <div class="barcode-label-card">
                        <div class="label-store-name">{{ $storeName }}</div>
                        <div class="label-item-name" title="{{ $lbl['name'] }}">{{ $lbl['name'] }}</div>
                        @if($format === '102x64' && !empty($lbl['code']))
                            <div class="label-item-meta">
                                <span>Code: <strong>{{ $lbl['code'] }}</strong></span>
                                @if(!empty($lbl['exp_date']))
                                    <span class="ml-2">| &nbsp;Exp: <strong>{{ $lbl['exp_date'] }}</strong></span>
                                @endif
                            </div>
                        @endif
                        <div class="label-barcode-wrap">
                            <svg class="label-barcode-svg" data-barcode="{{ $lbl['barcode'] }}"></svg>
                        </div>
                        <div class="label-prices">
                            @if($lbl['mrp'] > $lbl['sell_price'])
                                <span class="label-mrp">MRP: ₹{{ number_format($lbl['mrp'], 2) }}</span>
                            @else
                                <span class="small font-weight-bold text-muted">M.R.P. Incl. of Taxes</span>
                            @endif
                            <span class="label-sell">Price: ₹{{ number_format($lbl['sell_price'], 2) }}</span>
                            @if($format !== '102x64' && !empty($lbl['exp_date']))
                                <span class="small text-muted font-weight-bold">EXP: {{ $lbl['exp_date'] }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const is102x64 = document.body.classList.contains('format-102x64');

            // Render crisp, scannable barcodes with JsBarcode
            document.querySelectorAll('.label-barcode-svg').forEach(function (svgEl) {
                let code = (svgEl.getAttribute('data-barcode') || '').trim();
                if (!code) return;

                // Detect standard 13-digit EAN, 8-digit EAN, or fallback Code128
                let isEan13 = /^\d{13}$/.test(code);
                let isEan8  = /^\d{8}$/.test(code);
                let targetFormat = isEan13 ? "EAN13" : (isEan8 ? "EAN8" : "CODE128");

                let renderBarcode = function(fmt) {
                    JsBarcode(svgEl, code, {
                        format: fmt,
                        width: is102x64 ? 2.0 : 1.2,
                        height: is102x64 ? 58 : 28,
                        displayValue: true,
                        fontSize: is102x64 ? 13 : 9,
                        font: "monospace",
                        fontOptions: "bold",
                        margin: 1,
                        textMargin: is102x64 ? 2 : 0,
                        valid: function(valid) {
                            if (!valid && fmt !== "CODE128") {
                                renderBarcode("CODE128");
                            }
                        }
                    });
                };

                try {
                    renderBarcode(targetFormat);
                } catch (err) {
                    try {
                        renderBarcode("CODE128");
                    } catch (e2) {
                        console.warn("Could not render barcode for code:", code, e2);
                    }
                }
            });
        });
    </script>
</body>
</html>
