<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Barcode Stickers - {{ $storeName }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
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

        /* ── 2-Up Container (2 stickers side-by-side per row on 102mm / 4" roll) ─── */
        .format-50x25_2up .labels-container,
        .format-50x38_2up .labels-container,
        .format-50x50_2up .labels-container {
            width: 102mm;
            margin: 0 auto;
        }

        /* ── 50x25mm 2-Up (Standard Retail 2 Labels Across) ──────────────── */
        .format-50x25_2up .barcode-label-pair {
            width: 102mm;
            height: 25mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2mm auto;
            page-break-inside: avoid;
            break-inside: avoid;
            box-sizing: border-box;
        }
        .format-50x25_2up .barcode-label-card {
            width: 49.5mm;
            height: 24mm;
            max-height: 24mm;
            padding: 1mm 1.5mm;
            box-sizing: border-box;
            border: 1px dashed #94a3b8;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            text-align: center;
            border-radius: 3px;
        }
        .format-50x25_2up .label-store-name {
            font-size: 8pt;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #000;
        }
        .format-50x25_2up .label-item-name {
            font-size: 8.5pt;
            font-weight: 800;
            line-height: 1.15;
            max-height: 2.3em;
            overflow: hidden;
            margin: 0.3mm 0;
            color: #000;
            text-transform: uppercase;
        }
        .format-50x25_2up .label-barcode-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0.2mm 0;
        }
        .format-50x25_2up .label-barcode-svg {
            max-width: 98%;
            max-height: 13mm;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .format-50x25_2up .label-prices {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 7.5pt;
            font-weight: 700;
            line-height: 1.1;
            border-top: 0.8px solid #000;
            padding-top: 0.5mm;
        }
        .format-50x25_2up .label-mrp {
            font-size: 7pt;
            text-decoration: line-through;
            color: #334155;
        }
        .format-50x25_2up .label-sell {
            font-size: 10.5pt;
            font-weight: 900;
            color: #000;
        }

        /* ── 50x38mm 2-Up ─────────────────────────────────────────────────── */
        .format-50x38_2up .barcode-label-pair {
            width: 102mm;
            height: 38mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2mm auto;
            page-break-inside: avoid;
            break-inside: avoid;
            box-sizing: border-box;
        }
        .format-50x38_2up .barcode-label-card {
            width: 49.5mm;
            height: 36.5mm;
            max-height: 36.5mm;
            padding: 1.5mm 2mm;
            box-sizing: border-box;
            border: 1px dashed #94a3b8;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            text-align: center;
            border-radius: 3px;
        }
        .format-50x38_2up .label-store-name {
            font-size: 9pt;
            font-weight: 800;
            text-transform: uppercase;
        }
        .format-50x38_2up .label-item-name {
            font-size: 10pt;
            font-weight: 800;
            line-height: 1.2;
            max-height: 2.4em;
        }
        .format-50x38_2up .label-barcode-svg {
            max-width: 98%;
            max-height: 19mm;
        }
        .format-50x38_2up .label-prices {
            font-size: 9pt;
            font-weight: 700;
        }
        .format-50x38_2up .label-sell {
            font-size: 13.5pt;
            font-weight: 900;
        }

        /* ── Scale Size Modifiers (Normal / Large / Extra Large) ───────────── */
        body.scale-large .format-50x25_2up .label-store-name { font-size: 9pt; }
        body.scale-large .format-50x25_2up .label-item-name { font-size: 9.5pt; font-weight: 900; }
        body.scale-large .format-50x25_2up .label-barcode-svg { max-height: 14mm; }
        body.scale-large .format-50x25_2up .label-sell { font-size: 12pt; }
        body.scale-large .format-50x25_2up .label-mrp { font-size: 8pt; }

        body.scale-xl .format-50x25_2up .label-store-name { font-size: 10pt; }
        body.scale-xl .format-50x25_2up .label-item-name { font-size: 10.5pt; font-weight: 900; }
        body.scale-xl .format-50x25_2up .label-barcode-svg { max-height: 15mm; }
        body.scale-xl .format-50x25_2up .label-sell { font-size: 13.5pt; }

        body.scale-large .format-50x38_2up .label-store-name { font-size: 10.5pt; }
        body.scale-large .format-50x38_2up .label-item-name { font-size: 11.5pt; font-weight: 900; }
        body.scale-large .format-50x38_2up .label-barcode-svg { max-height: 22mm; }
        body.scale-large .format-50x38_2up .label-sell { font-size: 15.5pt; }

        body.scale-xl .format-50x38_2up .label-store-name { font-size: 12pt; }
        body.scale-xl .format-50x38_2up .label-item-name { font-size: 13pt; font-weight: 900; }
        body.scale-xl .format-50x38_2up .label-barcode-svg { max-height: 25mm; }
        body.scale-xl .format-50x38_2up .label-sell { font-size: 17.5pt; }

        /* ── 50x50mm 2-Up ─────────────────────────────────────────────────── */
        .format-50x50_2up .barcode-label-pair {
            width: 102mm;
            height: 50mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2mm auto;
            page-break-inside: avoid;
            break-inside: avoid;
            box-sizing: border-box;
        }
        .format-50x50_2up .barcode-label-card {
            width: 49.5mm;
            height: 48mm;
            max-height: 48mm;
            padding: 2mm 2.5mm;
            box-sizing: border-box;
            border: 1px dashed #94a3b8;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            text-align: center;
            border-radius: 3px;
        }

        /* ── 102x63.5mm 1-Up Label Style (Single 4" x 2.5" Thermal Roll) ─── */
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
        }
        .format-102x64 .label-store-name {
            font-size: 11pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #000;
            border-bottom: 1.5px solid #000;
            padding-bottom: 1mm;
            line-height: 1.2;
        }
        .format-102x64 .label-item-name {
            font-size: 11pt;
            font-weight: 700;
            line-height: 1.25;
            max-height: 2.5em;
            margin: 1mm 0 0.5mm 0;
            color: #000;
            text-transform: uppercase;
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
            border-top: 1.5px solid #000;
            padding-top: 1.2mm;
            margin-top: 0.5mm;
        }
        .format-102x64 .label-sell {
            font-size: 13.5pt;
            font-weight: 900;
            color: #000;
        }

        /* ── 50x25mm 1-Up Single Roll ─────────────────────────────────────── */
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
        }
        .format-50x25 .label-item-name {
            font-size: 7.5pt;
            font-weight: 700;
            line-height: 1.1;
            max-height: 2.2em;
        }
        .format-50x25 .label-barcode-svg {
            max-width: 98%;
            max-height: 10mm;
        }
        .format-50x25 .label-prices {
            display: flex;
            justify-content: space-around;
            font-size: 7pt;
            font-weight: 700;
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
        .format-a4 .label-store-name { font-size: 7.5pt; font-weight: 700; }
        .format-a4 .label-item-name { font-size: 7.5pt; font-weight: 600; line-height: 1.1; }
        .format-a4 .label-barcode-svg { max-width: 98%; height: 9mm; }
        .format-a4 .label-prices { display: flex; justify-content: space-around; font-size: 7pt; font-weight: 700; }

        .barcode-label-empty {
            visibility: hidden;
            border: none !important;
        }

        body.rotate-90 .barcode-label-card {
            transform: rotate(90deg);
        }
        body.rotate-180 .barcode-label-card {
            transform: rotate(180deg);
        }
        body.rotate-270 .barcode-label-card {
            transform: rotate(270deg);
        }

        /* ── Print Media Queries ───────────────────────────────────────────── */
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
            .barcode-label-empty {
                visibility: hidden !important;
            }

            body.test-print-mode .barcode-label-pair:not(:first-child) {
                display: none !important;
            }

            body.rotate-90 .barcode-label-card {
                transform: rotate(90deg);
            }
            body.rotate-180 .barcode-label-card {
                transform: rotate(180deg);
            }
            body.rotate-270 .barcode-label-card {
                transform: rotate(270deg);
            }

            /* 50x25mm 2-Up */
            .format-50x25_2up html, .format-50x25_2up body {
                width: 102mm !important;
            }
            .format-50x25_2up .barcode-label-pair {
                width: 102mm !important;
                height: 24.5mm !important;
                max-height: 24.5mm !important;
                margin: 0 !important;
                padding: 0 !important;
                display: flex !important;
                justify-content: space-between !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
            }
            .format-50x25_2up .barcode-label-pair:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }
            .format-50x25_2up .barcode-label-card {
                width: 49.5mm !important;
                height: 24.5mm !important;
                max-height: 24.5mm !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            /* 50x38mm 2-Up */
            .format-50x38_2up html, .format-50x38_2up body {
                width: 102mm !important;
            }
            .format-50x38_2up .barcode-label-pair {
                width: 102mm !important;
                height: 37.5mm !important;
                max-height: 37.5mm !important;
                margin: 0 !important;
                padding: 0 !important;
                display: flex !important;
                justify-content: space-between !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
            }
            .format-50x38_2up .barcode-label-pair:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }
            .format-50x38_2up .barcode-label-card {
                width: 49.5mm !important;
                height: 37.5mm !important;
                max-height: 37.5mm !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            /* 50x50mm 2-Up */
            .format-50x50_2up html, .format-50x50_2up body {
                width: 102mm !important;
            }
            .format-50x50_2up .barcode-label-pair {
                width: 102mm !important;
                height: 49.5mm !important;
                max-height: 49.5mm !important;
                margin: 0 !important;
                padding: 0 !important;
                display: flex !important;
                justify-content: space-between !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
            }
            .format-50x50_2up .barcode-label-pair:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }

            /* 102x63.5mm 1-Up Single */
            .format-102x64 html, .format-102x64 body {
                width: 102mm !important;
            }
            .format-102x64 .barcode-label-card {
                width: 102mm !important;
                height: 61.5mm !important;
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

            /* 50x25mm 1-Up Single */
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

            /* A4 Sheet */
            .format-a4 .labels-container {
                box-shadow: none !important;
                padding: 0 !important;
                width: 100% !important;
            }
        }
    </style>

    {{-- @page Dimensions Setup --}}
    @if($format === '50x25_2up')
    <style>
        @page {
            size: 102mm 25mm;
            margin: 0mm !important;
        }
    </style>
    @elseif($format === '50x38_2up')
    <style>
        @page {
            size: 102mm 38mm;
            margin: 0mm !important;
        }
    </style>
    @elseif($format === '50x50_2up')
    <style>
        @page {
            size: 102mm 50mm;
            margin: 0mm !important;
        }
    </style>
    @elseif($format === '102x64')
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
                <a href="{{ request()->fullUrlWithQuery(['format' => '50x25_2up']) }}" class="btn {{ $format === '50x25_2up' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-primary' }}" title="TSC TE244: 2 Stickers Per Row (50x25 mm each)">
                    <i class="fas fa-th-large mr-1"></i> 50x25 mm (2-Up Roll)
                </a>
                <a href="{{ request()->fullUrlWithQuery(['format' => '50x38_2up']) }}" class="btn {{ $format === '50x38_2up' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-primary' }}" title="TSC TE244: 2 Stickers Per Row (50x38 mm each)">
                    <i class="fas fa-th-large mr-1"></i> 50x38 mm (2-Up Roll)
                </a>
                <a href="{{ request()->fullUrlWithQuery(['format' => '50x50_2up']) }}" class="btn {{ $format === '50x50_2up' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-primary' }}" title="TSC TE244: 2 Stickers Per Row (50x50 mm each)">
                    <i class="fas fa-th-large mr-1"></i> 50x50 mm (2-Up Roll)
                </a>
                <a href="{{ request()->fullUrlWithQuery(['format' => '102x64']) }}" class="btn {{ $format === '102x64' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-primary' }}" title="TSC TE244: 1 Single Big Label (102x63.5 mm)">
                    102x63.5 mm (1-Up Single)
                </a>
                <a href="{{ request()->fullUrlWithQuery(['format' => '50x25']) }}" class="btn {{ $format === '50x25' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-primary' }}">
                    50x25 mm (1-Up)
                </a>
                <a href="{{ request()->fullUrlWithQuery(['format' => 'a4']) }}" class="btn {{ $format === 'a4' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-primary' }}">
                    A4 Sheet (40-Up)
                </a>
            </div>

            {{-- Rotation Selector --}}
            <div class="btn-group btn-group-sm mr-2 my-1">
                <button type="button" class="btn btn-outline-secondary font-weight-bold dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-sync-alt mr-1"></i> <span id="rotLabel">Rotation: 0°</span>
                </button>
                <div class="dropdown-menu dropdown-menu-right shadow">
                    <a class="dropdown-item font-weight-bold" href="javascript:void(0)" onclick="setRotation(0)"><i class="fas fa-arrow-up mr-2 text-primary"></i> 0° Sidha (Normal Portrait)</a>
                    <a class="dropdown-item font-weight-bold" href="javascript:void(0)" onclick="setRotation(90)"><i class="fas fa-arrow-right mr-2 text-warning"></i> 90° Ghumao (Clockwise)</a>
                    <a class="dropdown-item font-weight-bold" href="javascript:void(0)" onclick="setRotation(180)"><i class="fas fa-arrow-down mr-2 text-danger"></i> 180° Inverted (Ulta)</a>
                    <a class="dropdown-item font-weight-bold" href="javascript:void(0)" onclick="setRotation(270)"><i class="fas fa-arrow-left mr-2 text-info"></i> 270° Ghumao (Counter-CW)</a>
                </div>
            </div>

            {{-- Text & Barcode Size / Scale Selector --}}
            <div class="btn-group btn-group-sm mr-2 my-1">
                <button type="button" class="btn btn-outline-dark font-weight-bold dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-text-height mr-1"></i> <span id="scaleLabel">Size: Bada (Large)</span>
                </button>
                <div class="dropdown-menu dropdown-menu-right shadow">
                    <a class="dropdown-item font-weight-bold" href="javascript:void(0)" onclick="setScale('normal')">Chota (Normal)</a>
                    <a class="dropdown-item font-weight-bold text-primary" href="javascript:void(0)" onclick="setScale('large')"><i class="fas fa-check text-primary mr-1"></i> Bada (Large - Recommended)</a>
                    <a class="dropdown-item font-weight-bold text-success" href="javascript:void(0)" onclick="setScale('xl')"><i class="fas fa-expand-arrows-alt text-success mr-1"></i> Bahut Bada (Extra Large)</a>
                </div>
            </div>

            {{-- Guide Modal Trigger --}}
            <button type="button" class="btn btn-outline-primary btn-sm font-weight-bold shadow-sm px-3 my-1 mr-2" data-toggle="modal" data-target="#tscSetupModal">
                <i class="fas fa-wrench mr-1"></i> TSC Setup Guide
            </button>

            {{-- Test Print (1 Row Only) --}}
            <button type="button" class="btn btn-warning btn-sm font-weight-bold shadow-sm px-3 my-1 mr-2" onclick="printTestRow()" title="Sirf 2 stickers test ke liye print karein taaki roll na kharab ho">
                <i class="fas fa-vial mr-1"></i> Test 1 Row (2 Stickers)
            </button>

            {{-- Main Print All Stickers --}}
            <button type="button" class="btn btn-success font-weight-bold shadow-sm px-4 my-1" onclick="window.print();">
                <i class="fas fa-print mr-2"></i> Print All Stickers
            </button>
        </div>
    </div>

    <!-- On-screen Guide for TSC TE244 (2-Up Roll) -->
    <div class="container-fluid print-instructions mt-3">
        <div class="alert alert-warning border border-warning shadow-sm py-2 px-3 mb-0 d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center my-1">
                <i class="fas fa-exclamation-triangle text-dark mr-2" style="font-size: 22px;"></i>
                <span class="small font-weight-bold text-dark">
                    <strong>TSC TE244 (2-Up Roll) Print Guide:</strong> &nbsp;
                    1. Layout = <span class="badge badge-danger" style="font-size: 11px;">PORTRAIT</span> (Landscape nahi rakhna hai) &nbsp;|&nbsp;
                    2. Paper Size = <strong>102 x 25 mm</strong> (ya 102 x 38 mm) &nbsp;|&nbsp;
                    3. Margins = <strong>None</strong> &nbsp;|&nbsp;
                    4. Headers & Footers = <strong>OFF (Uncheck)</strong>
                </span>
            </div>
            <div class="my-1">
                <span class="badge badge-dark px-2 py-1">2 Stickers Side-by-Side (2-Up)</span>
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
            @php
                $is2Up = str_ends_with($format, '_2up');
            @endphp

            @if($is2Up)
                {{-- 2-Up Layout: 2 stickers paired side-by-side per row across the 102mm roll --}}
                <div class="labels-container">
                    @foreach(array_chunk($labels, 2) as $pair)
                        <div class="barcode-label-pair">
                            @foreach($pair as $lbl)
                                <div class="barcode-label-card">
                                    <div class="label-store-name">{{ $storeName }}</div>
                                    <div class="label-item-name" title="{{ $lbl['name'] }}">
                                        {{ $lbl['name'] }}
                                        @if(!empty($lbl['code']))
                                            <span style="font-size: 6pt; font-weight: 800; color: #475569; margin-left: 1mm;">#{{ $lbl['code'] }}</span>
                                        @endif
                                    </div>
                                    <div class="label-barcode-wrap">
                                        <svg class="label-barcode-svg" data-barcode="{{ $lbl['barcode'] }}"></svg>
                                    </div>
                                    <div class="label-prices">
                                        @if($lbl['mrp'] > $lbl['sell_price'])
                                            <span class="label-mrp">MRP: ₹{{ number_format($lbl['mrp'], 2) }}</span>
                                        @else
                                            <span class="small font-weight-bold text-muted" style="font-size: 5pt;">INCL. TAX</span>
                                        @endif
                                        <span class="label-sell">₹{{ number_format($lbl['sell_price'], 2) }}</span>
                                        @if(!empty($lbl['exp_date']))
                                            <span class="label-exp">{{ substr($lbl['exp_date'], 5) }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                            @if(count($pair) === 1)
                                {{-- Empty invisible placeholder to preserve left alignment --}}
                                <div class="barcode-label-card barcode-label-empty"></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                {{-- 1-Up Single Roll or A4 Grid --}}
                <div class="labels-container">
                    @foreach ($labels as $lbl)
                        <div class="barcode-label-card">
                            <div class="label-store-name">{{ $storeName }}</div>
                            <div class="label-item-name" title="{{ $lbl['name'] }}">{{ $lbl['name'] }}</div>
                            @if($format === '102x64' && !empty($lbl['code']))
                                <div class="label-item-meta" style="font-size: 8pt; font-weight: 600; color: #334155; margin-bottom: 0.5mm;">
                                    <span>Code: <strong>{{ $lbl['code'] }}</strong></span>
                                    @if(!empty($lbl['exp_date']))
                                        <span class="ml-2">| &nbsp;Exp: <strong>{{ $lbl['exp_date'] }}</strong></span>
                                    @endif
                                </div>
                            @endif
                            <div class="label-barcode-wrap" style="display: flex; justify-content: center; align-items: center; margin: 0.5mm 0;">
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
        @endif
    </div>

    <!-- TSC TE244 Setup Modal -->
    <div class="modal fade" id="tscSetupModal" tabindex="-1" role="dialog" aria-labelledby="tscSetupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white py-3">
                    <h5 class="modal-title font-weight-bold" id="tscSetupModalLabel">
                        <i class="fas fa-print text-warning mr-2"></i> TSC TE244 (2-Up Barcode Roll) Step-by-Step Setup Guide
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4" style="font-size: 14px; line-height: 1.6;">
                    <div class="alert alert-info py-2 px-3 mb-3">
                        <strong>Aapke Roll me 1 row me 2 stickers bagal-bagal hain (50x25mm ya 50x38mm).</strong><br>
                        Print <strong>sideways (ghoom ke)</strong> ya <strong>blank roll bahar nikalne</strong> se bachne ke liye sirf ye 3 steps check karein:
                    </div>

                    <div class="card mb-3 border-primary">
                        <div class="card-header bg-light font-weight-bold text-primary">
                            <i class="fas fa-magic mr-1"></i> STEP 1: Printer Hardware Calibrate Karein (Blank Roll / Paper Fenkte Rehne se Bachne Ke Liye)
                        </div>
                        <div class="card-body py-2">
                            <ol class="mb-0 pl-3">
                                <li>Printer ko peeche ke button se <strong>POWER OFF</strong> karein.</li>
                                <li>Front panel par <strong>PAUSE</strong> button (ya FEED button) ko ungli se daba kar rakhein.</li>
                                <li>Button dabaye hue hi printer ko peeche se <strong>POWER ON</strong> karein.</li>
                                <li>Jaise hi printer paper ko aage khiskana shuru kare aur light blink ho, button chhod dein.</li>
                                <li>Printer 2-3 stickers nikal kar exact gap par ruk jayega (Sensor calibrate ho gaya).</li>
                            </ol>
                        </div>
                    </div>

                    <div class="card mb-3 border-success">
                        <div class="card-header bg-light font-weight-bold text-success">
                            <i class="fas fa-cogs mr-1"></i> STEP 2: Windows me TSC TE244 Driver Settings (Sirf 1 baar karni hoti hai)
                        </div>
                        <div class="card-body py-2">
                            <ol class="mb-0 pl-3">
                                <li>Windows Start menu me search karein <strong>"Printers & Scanners"</strong> aur open karein.</li>
                                <li><strong>TSC TE244</strong> printer par click karein -> <strong>Printing Preferences</strong>.</li>
                                <li><strong>Page Setup</strong> tab me click karein <strong>New...</strong>:
                                    <ul>
                                        <li>Name: <code>102x25</code> (ya <code>102x38</code>)</li>
                                        <li>Width: <strong>102.0 mm</strong> (roll ki total choudai)</li>
                                        <li>Height: <strong>25.0 mm</strong> (ya sticker ki height jaise 38.0 mm)</li>
                                    </ul>
                                </li>
                                <li><strong>Stock</strong> tab me:
                                    <ul>
                                        <li>Type: <strong>Labels with Gaps</strong> (Gap height: <strong>2.0 mm</strong>)</li>
                                    </ul>
                                </li>
                                <li><strong>Apply</strong> aur <strong>OK</strong> par click karein.</li>
                            </ol>
                        </div>
                    </div>

                    <div class="card mb-3 border-warning">
                        <div class="card-header bg-light font-weight-bold text-dark">
                            <i class="fas fa-desktop mr-1"></i> STEP 3: Chrome Print Dialog Settings (Ctrl + P)
                        </div>
                        <div class="card-body py-2">
                            <ul class="mb-0 pl-3">
                                <li><strong>Destination:</strong> Select <code>TSC TE244</code></li>
                                <li><strong>Layout:</strong> <span class="badge badge-danger">PORTRAIT</span> (Landscape nahi rakhna hai, Portrait se print sidha aayega)</li>
                                <li><strong>Paper size:</strong> <code>102x25 mm</code> (jo step 2 me banaya)</li>
                                <li><strong>Margins:</strong> <code>None</code> (0)</li>
                                <li><strong>Scale:</strong> <code>100%</code> (Custom -> 100)</li>
                                <li><strong>Headers and Footers:</strong> <code>Uncheck (OFF)</code></li>
                            </ul>
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 mb-0 font-weight-bold">
                        💡 Roll bachane ke liye pehle <strong>"Test 1 Row (2 Stickers)"</strong> button dabakar test karein!
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning font-weight-bold" onclick="$('#tscSetupModal').modal('hide'); printTestRow();">
                        <i class="fas fa-vial mr-1"></i> Test 1 Row Print Karein
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setRotation(deg) {
            document.body.classList.remove('rotate-0', 'rotate-90', 'rotate-180', 'rotate-270');
            if (deg > 0) {
                document.body.classList.add('rotate-' + deg);
            }
            const labels = {
                0: 'Rotation: 0°',
                90: 'Rotation: 90°',
                180: 'Rotation: 180°',
                270: 'Rotation: 270°'
            };
            const labelEl = document.getElementById('rotLabel');
            if (labelEl) {
                labelEl.innerText = labels[deg] || 'Rotation: ' + deg + '°';
            }
            try {
                localStorage.setItem('urbanpos_barcode_rotation', deg);
            } catch (e) {}
        }

        function setScale(scaleName) {
            document.body.classList.remove('scale-normal', 'scale-large', 'scale-xl');
            document.body.classList.add('scale-' + scaleName);
            const labels = {
                'normal': 'Size: Chota (Normal)',
                'large': 'Size: Bada (Large)',
                'xl': 'Size: Extra Large'
            };
            const el = document.getElementById('scaleLabel');
            if (el) {
                el.innerText = labels[scaleName] || 'Size: ' + scaleName;
            }
            try {
                localStorage.setItem('urbanpos_barcode_scale', scaleName);
            } catch (e) {}
            renderAllBarcodes();
        }

        function printTestRow() {
            document.body.classList.add('test-print-mode');
            window.print();
            setTimeout(function () {
                document.body.classList.remove('test-print-mode');
            }, 1200);
        }

        function renderAllBarcodes() {
            const is102x64 = document.body.classList.contains('format-102x64');
            const is50x38 = document.body.classList.contains('format-50x38_2up');
            const is50x50 = document.body.classList.contains('format-50x50_2up');
            const isScaleLarge = document.body.classList.contains('scale-large');
            const isScaleXl = document.body.classList.contains('scale-xl');

            let barWidth = 1.35;
            let barHeight = 28;
            let fontSize = 9.5;

            if (is102x64) {
                barWidth = 2.0;
                barHeight = 58;
                fontSize = 13;
            } else if (is50x38) {
                barWidth = isScaleXl ? 1.6 : (isScaleLarge ? 1.45 : 1.3);
                barHeight = isScaleXl ? 52 : (isScaleLarge ? 44 : 36);
                fontSize = isScaleXl ? 12.5 : (isScaleLarge ? 11 : 10);
            } else if (is50x50) {
                barWidth = isScaleXl ? 1.65 : 1.45;
                barHeight = isScaleXl ? 54 : 46;
                fontSize = 12;
            } else {
                // 50x25_2up or 1-Up
                barWidth = isScaleXl ? 1.5 : (isScaleLarge ? 1.35 : 1.15);
                barHeight = isScaleXl ? 32 : (isScaleLarge ? 28 : 22);
                fontSize = isScaleXl ? 10.5 : (isScaleLarge ? 9.5 : 8.5);
            }

            // Render crisp, scannable barcodes with JsBarcode
            document.querySelectorAll('.label-barcode-svg').forEach(function (svgEl) {
                let code = (svgEl.getAttribute('data-barcode') || '').trim();
                if (!code) return;

                let isEan13 = /^\d{13}$/.test(code);
                let isEan8  = /^\d{8}$/.test(code);
                let targetFormat = isEan13 ? "EAN13" : (isEan8 ? "EAN8" : "CODE128");

                let renderBarcode = function(fmt) {
                    JsBarcode(svgEl, code, {
                        format: fmt,
                        width: barWidth,
                        height: barHeight,
                        displayValue: true,
                        fontSize: fontSize,
                        font: "monospace",
                        fontOptions: "bold",
                        margin: 0,
                        textMargin: 1,
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
        }

        document.addEventListener("DOMContentLoaded", function () {
            // Restore saved rotation preference
            try {
                const savedRot = parseInt(localStorage.getItem('urbanpos_barcode_rotation') || '0', 10);
                if (savedRot) {
                    setRotation(savedRot);
                }
            } catch (e) {}

            // Restore saved scale preference (default to 'large' for bold readable text)
            try {
                const savedScale = localStorage.getItem('urbanpos_barcode_scale') || 'large';
                setScale(savedScale);
            } catch (e) {
                renderAllBarcodes();
            }
        });
    </script>
</body>
</html>
