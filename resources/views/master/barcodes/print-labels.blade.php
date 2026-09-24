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
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #0f172a;
        }

        /* Toolbar (Hidden on Print) */
        .print-toolbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        /* 102x63.5mm Label Style (TSC TE244 4" x 2.5" Thermal Roll) */
        .format-102x64 .barcode-label-card {
            width: 102mm;
            height: 63.5mm;
            padding: 2.5mm 4mm;
            box-sizing: border-box;
            border: 1px dashed #94a3b8;
            margin: 3mm auto;
            background: #ffffff;
            page-break-inside: avoid;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            text-align: center;
            border-radius: 4px;
        }
        .format-102x64 .label-store-name {
            font-size: 11pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 1mm;
        }
        .format-102x64 .label-item-name {
            font-size: 11pt;
            font-weight: 700;
            line-height: 1.25;
            max-height: 2.5em;
            margin: 1mm 0;
            color: #1e293b;
        }
        .format-102x64 .label-barcode-svg {
            height: 24mm;
            width: 90%;
            margin: 0 auto;
        }
        .format-102x64 .label-prices {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10.5pt;
            font-weight: 700;
            line-height: 1.2;
            border-top: 1.5px solid #0f172a;
            padding-top: 1.5mm;
            margin-top: 1mm;
        }
        .format-102x64 .label-mrp {
            font-size: 9.5pt;
            text-decoration: line-through;
            color: #64748b;
        }
        .format-102x64 .label-sell {
            font-size: 13pt;
            font-weight: 900;
            color: #047857;
        }

        /* 50x25mm Label Style (Standard 1-Up Roll) */
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

        /* 38x25mm Label Style (Compact 2-Up Roll) */
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

        /* A4 Sheet Matrix (4 Columns x 10 Rows) */
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

        .label-store-name {
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .label-item-name {
            font-size: 7.5pt;
            font-weight: 600;
            line-height: 1.1;
            max-height: 2.2em;
            overflow: hidden;
            margin: 0.5mm 0;
        }

        .label-barcode-svg {
            width: 100%;
            height: 9mm;
            display: block;
            margin: 0 auto;
        }

        .label-prices {
            display: flex;
            justify-content: space-around;
            align-items: center;
            font-size: 7pt;
            font-weight: 700;
            line-height: 1;
            border-top: 0.5px solid #000;
            padding-top: 0.5mm;
        }

        .label-mrp {
            text-decoration: line-through;
            font-weight: 500;
            color: #475569;
        }

        /* Print Media Queries */
        @media print {
            .print-toolbar {
                display: none !important;
            }
            body {
                background: transparent !important;
            }
            .barcode-label-card {
                border: none !important;
                box-shadow: none !important;
            }
            .format-50x25 .barcode-label-card {
                margin: 0 !important;
                page-break-after: always;
            }
            .format-102x64 {
                margin: 0 !important;
                padding: 0 !important;
            }
            .format-102x64 .barcode-label-card {
                margin: 0 auto !important;
                page-break-after: always;
                break-after: page;
                width: 102mm !important;
                height: 63.5mm !important;
            }
            .format-a4 .labels-container {
                box-shadow: none !important;
                padding: 0 !important;
                width: 100% !important;
            }
        }
    </style>
    @if($format === '102x64')
    <style>
        @page {
            size: 102mm 63.5mm;
            margin: 0;
        }
    </style>
    @endif
</head>
<body class="format-{{ $format }}">

    <!-- Toolbar -->
    <div class="print-toolbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <button type="button" class="btn btn-secondary btn-sm mr-3 font-weight-bold" onclick="window.history.back();">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </button>
            <h5 class="mb-0 font-weight-bold text-dark mr-3">
                <i class="fas fa-barcode text-primary mr-2"></i>Barcode Labels
            </h5>
            <span class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size: 13px;">
                Total: {{ count($labels) }} Stickers
            </span>
        </div>

        <div class="d-flex align-items-center">
            <div class="btn-group btn-group-sm mr-3">
                <a href="{{ request()->fullUrlWithQuery(['format' => '102x64']) }}" class="btn btn-outline-primary {{ $format === '102x64' ? 'active' : '' }}">
                    <i class="fas fa-tag mr-1"></i> 102x63.5 mm (TSC TE244)
                </a>
                <a href="{{ request()->fullUrlWithQuery(['format' => '50x25']) }}" class="btn btn-outline-primary {{ $format === '50x25' ? 'active' : '' }}">
                    50x25 mm (1-Up Roll)
                </a>
                <a href="{{ request()->fullUrlWithQuery(['format' => '38x25']) }}" class="btn btn-outline-primary {{ $format === '38x25' ? 'active' : '' }}">
                    38x25 mm (2-Up Roll)
                </a>
                <a href="{{ request()->fullUrlWithQuery(['format' => 'a4']) }}" class="btn btn-outline-primary {{ $format === 'a4' ? 'active' : '' }}">
                    A4 Sheet (40-Up)
                </a>
            </div>

            <button type="button" class="btn btn-success font-weight-bold shadow-sm px-4" onclick="window.print();">
                <i class="fas fa-print mr-2"></i> Print Stickers
            </button>
        </div>
    </div>

    <!-- Labels Preview Container -->
    <div class="py-4">
        @if(empty($labels))
            <div class="text-center py-5">
                <i class="fas fa-barcode text-muted" style="font-size: 48px;"></i>
                <h4 class="mt-3 font-weight-bold text-secondary">No Items Selected for Label Printing</h4>
                <p class="text-muted">Select items from Item Master or open a Purchase Invoice to print labels.</p>
                <button type="button" class="btn btn-primary font-weight-bold mt-2" onclick="window.history.back();">Go Back</button>
            </div>
        @else
            <div class="labels-container">
                @foreach ($labels as $lbl)
                    <div class="barcode-label-card">
                        <div class="label-store-name">{{ $storeName }}</div>
                        <div class="label-item-name" title="{{ $lbl['name'] }}">{{ $lbl['name'] }}</div>
                        <div>
                            <svg class="label-barcode-svg" data-barcode="{{ $lbl['barcode'] }}"></svg>
                        </div>
                        <div class="label-prices">
                            @if($lbl['mrp'] > $lbl['sell_price'])
                                <span class="label-mrp">MRP: ₹{{ number_format($lbl['mrp'], 2) }}</span>
                            @endif
                            <span class="label-sell">Price: ₹{{ number_format($lbl['sell_price'], 2) }}</span>
                            @if(!empty($lbl['exp_date']))
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

            // Render Code128 barcodes via JsBarcode
            document.querySelectorAll('.label-barcode-svg').forEach(function (svgEl) {
                let code = svgEl.getAttribute('data-barcode');
                if (code) {
                    try {
                        JsBarcode(svgEl, code, {
                            format: "CODE128",
                            width: is102x64 ? 1.8 : 1.2,
                            height: is102x64 ? 54 : 28,
                            displayValue: true,
                            fontSize: is102x64 ? 13 : 9,
                            margin: 1,
                            textMargin: is102x64 ? 2 : 0
                        });
                    } catch (e) {
                        console.warn("Could not render barcode for code:", code, e);
                    }
                }
            });
        });
    </script>
</body>
</html>
