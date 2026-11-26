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
            font-size: 7.5pt;
            font-weight: 700;
            border-top: 1px solid #000;
            padding-top: 0.3mm;
            margin: 0;
        }
        .format-50x25_2up .label-prices-flex {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            width: 100%;
        }
        .format-50x25_2up .label-mrp {
            font-size: 6.5pt;
            text-decoration: line-through;
            color: #334155;
            white-space: nowrap;
        }
        .format-50x25_2up .label-mrp-plain {
            font-size: 6.5pt;
            font-weight: 700;
            color: #334155;
            white-space: nowrap;
        }
        .format-50x25_2up .label-sell {
            font-size: 10.5pt;
            font-weight: 900;
            color: #000;
            white-space: nowrap;
        }

        /* ── 50x38mm 2-Up ─────────────────────────────────────────────────── */
        .format-50x38_2up .barcode-label-pair {
            width: 102mm;
            height: 37mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1mm auto;
            page-break-inside: avoid;
            break-inside: avoid;
            box-sizing: border-box;
        }
        .format-50x38_2up .barcode-label-card {
            width: 49.5mm;
            height: 36mm;
            max-height: 36mm;
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
        .format-50x38_2up .label-store-name {
            font-size: 8pt;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
        }
        .format-50x38_2up .label-item-name {
            font-size: 8.5pt;
            font-weight: 700;
            line-height: 1.15;
            max-height: 2.3em;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            word-break: break-word;
            color: #000;
            margin: 0.3mm 0;
        }
        .format-50x38_2up .label-barcode-svg {
            max-width: 98%;
            max-height: 15mm;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .format-50x38_2up .label-prices {
            font-size: 8pt;
            font-weight: 700;
            border-top: 1px solid #000;
            padding-top: 0.5mm;
            margin: 0;
        }
        .format-50x38_2up .label-prices-flex {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            width: 100%;
        }
        .format-50x38_2up .label-mrp {
            font-size: 7.5pt;
            text-decoration: line-through;
            color: #334155;
            white-space: nowrap;
        }
        .format-50x38_2up .label-mrp-plain {
            font-size: 7.5pt;
            font-weight: 700;
            color: #334155;
            white-space: nowrap;
        }
        .format-50x38_2up .label-sell {
            font-size: 11.5pt;
            font-weight: 900;
            color: #000;
            white-space: nowrap;
        }

        /* ── Scale Size Modifiers (Normal / Large / Extra Large) ───────────── */
        body.scale-large.format-50x25_2up .label-store-name, body.scale-large .format-50x25_2up .label-store-name { font-size: 8.5pt; }
        body.scale-large.format-50x25_2up .label-item-name, body.scale-large .format-50x25_2up .label-item-name { font-size: 9pt; font-weight: 800; }
        body.scale-large.format-50x25_2up .label-barcode-svg, body.scale-large .format-50x25_2up .label-barcode-svg { max-height: 12mm; }
        body.scale-large.format-50x25_2up .label-sell, body.scale-large .format-50x25_2up .label-sell { font-size: 11pt; }
        body.scale-large.format-50x25_2up .label-mrp, body.scale-large .format-50x25_2up .label-mrp { font-size: 7.5pt; }

        body.scale-xl.format-50x25_2up .label-store-name, body.scale-xl .format-50x25_2up .label-store-name { font-size: 9pt; }
        body.scale-xl.format-50x25_2up .label-item-name, body.scale-xl .format-50x25_2up .label-item-name { font-size: 9.5pt; font-weight: 800; }
        body.scale-xl.format-50x25_2up .label-barcode-svg, body.scale-xl .format-50x25_2up .label-barcode-svg { max-height: 13mm; }
        body.scale-xl.format-50x25_2up .label-sell, body.scale-xl .format-50x25_2up .label-sell { font-size: 12pt; }

        body.scale-large.format-50x38_2up .label-store-name, body.scale-large .format-50x38_2up .label-store-name { font-size: 9pt; }
        body.scale-large.format-50x38_2up .label-item-name, body.scale-large .format-50x38_2up .label-item-name { font-size: 9.5pt; font-weight: 800; }
        body.scale-large.format-50x38_2up .label-barcode-svg, body.scale-large .format-50x38_2up .label-barcode-svg { max-height: 14mm; }
        body.scale-large.format-50x38_2up .label-sell, body.scale-large .format-50x38_2up .label-sell { font-size: 13pt; }

        body.scale-xl.format-50x38_2up .label-store-name, body.scale-xl .format-50x38_2up .label-store-name { font-size: 10pt; }
        body.scale-xl.format-50x38_2up .label-item-name, body.scale-xl .format-50x38_2up .label-item-name { font-size: 10.5pt; font-weight: 800; }
        body.scale-xl.format-50x38_2up .label-barcode-svg, body.scale-xl .format-50x38_2up .label-barcode-svg { max-height: 15mm; }
        body.scale-xl.format-50x38_2up .label-sell, body.scale-xl .format-50x38_2up .label-sell { font-size: 14pt; }

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

        /* ── A4 Sheet Page Container (One 210mm x 297mm physical sheet per page) ── */
        .a4-sheet-page {
            width: 210mm;
            height: 297mm;
            max-height: 297mm;
            margin: 15px auto;
            background: #ffffff;
            box-sizing: border-box;
            box-shadow: 0 4px 10px rgba(0,0,0,0.12);
            page-break-after: always;
            break-after: page;
            page-break-inside: avoid;
            break-inside: avoid;
            overflow: hidden;
        }

        /* ── Format A4 - 24 Labels (3 Columns x 8 Rows - Standard Indian Desmat/Avery) ── */
        .format-a4_24 .a4-sheet-page,
        .format-a4 .a4-sheet-page {
            padding: 9.5mm 6mm;
            display: grid;
            grid-template-columns: repeat(3, 64mm);
            grid-template-rows: repeat(8, 34mm);
            column-gap: 3mm;
            row-gap: 0.8mm;
        }
        .format-a4_24 .barcode-label-card,
        .format-a4 .barcode-label-card {
            width: 64mm;
            height: 34mm;
            max-height: 34mm;
            padding: 1.5mm 2.5mm;
            box-sizing: border-box;
            border: 1px dashed #cbd5e1;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            text-align: center;
            border-radius: 2px;
        }
        .format-a4_24 .label-store-name,
        .format-a4 .label-store-name {
            font-size: 8pt;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #000;
        }
        .format-a4_24 .label-item-name,
        .format-a4 .label-item-name {
            font-size: 8.5pt;
            font-weight: 800;
            line-height: 1.15;
            max-height: 2.3em;
            overflow: hidden;
            color: #000;
            text-transform: uppercase;
        }
        .format-a4_24 .label-barcode-svg,
        .format-a4 .label-barcode-svg {
            max-width: 98%;
            max-height: 13.5mm;
            display: block;
            margin: 0 auto;
        }
        .format-a4_24 .label-prices,
        .format-a4 .label-prices {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 7.5pt;
            font-weight: 700;
            border-top: 0.8px solid #000;
            padding-top: 0.5mm;
        }
        .format-a4_24 .label-sell,
        .format-a4 .label-sell {
            font-size: 11pt;
            font-weight: 900;
            color: #000;
        }
        .format-a4_24 .label-mrp,
        .format-a4 .label-mrp {
            font-size: 7pt;
            text-decoration: line-through;
            color: #334155;
        }
        .format-a4_24 .label-exp,
        .format-a4 .label-exp {
            font-size: 6.5pt;
            font-weight: bold;
            color: #475569;
        }

        /* ── Format A4 - 40 Labels (4 Columns x 10 Rows) ─────────────── */
        .format-a4_40 .a4-sheet-page {
            padding: 10mm 5mm;
            display: grid;
            grid-template-columns: repeat(4, 48mm);
            grid-template-rows: repeat(10, 26mm);
            column-gap: 2.5mm;
            row-gap: 1.5mm;
        }
        .format-a4_40 .barcode-label-card {
            width: 48mm;
            height: 26mm;
            max-height: 26mm;
            padding: 1mm 1.5mm;
            box-sizing: border-box;
            border: 1px dashed #cbd5e1;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            text-align: center;
            border-radius: 2px;
        }
        .format-a4_40 .label-store-name {
            font-size: 6.5pt;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.1;
        }
        .format-a4_40 .label-item-name {
            font-size: 7pt;
            font-weight: 800;
            line-height: 1.1;
            max-height: 2.1em;
            overflow: hidden;
        }
        .format-a4_40 .label-barcode-svg {
            max-width: 98%;
            max-height: 10mm;
            display: block;
            margin: 0 auto;
        }
        .format-a4_40 .label-prices {
            display: flex;
            justify-content: space-between;
            font-size: 6.5pt;
            font-weight: 700;
            border-top: 0.5px solid #000;
            padding-top: 0.3mm;
        }
        .format-a4_40 .label-sell {
            font-size: 9pt;
            font-weight: 900;
        }
        .format-a4_40 .label-mrp {
            font-size: 6pt;
            text-decoration: line-through;
            color: #475569;
        }
        .format-a4_40 .label-exp {
            font-size: 6pt;
            color: #64748b;
        }

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

        @page {
            margin: 0mm !important;
            @if($format === '50x38_2up')
            size: 102mm 38mm;
            @elseif($format === '50x25_2up')
            size: 102mm 25mm;
            @elseif($format === '50x50_2up')
            size: 102mm 50mm;
            @elseif($format === '102x64')
            size: 102mm 64mm;
            @elseif($format === '50x25')
            size: 50mm 25mm;
            @elseif(in_array($format, ['a4', 'a4_24', 'a4_40']))
            size: 210mm 297mm;
            @endif
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

            body.test-print-mode .barcode-label-pair:not(:first-child),
            body.test-print-mode .barcode-label-card:not(:first-child) {
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
            body.format-50x25_2up, .format-50x25_2up html, .format-50x25_2up body {
                width: 102mm !important;
            }
            body.format-50x25_2up .barcode-label-pair, .format-50x25_2up .barcode-label-pair {
                width: 102mm !important;
                height: 25.0mm !important;
                max-height: 25.0mm !important;
                margin: 0 !important;
                padding: 0.5mm 1mm !important;
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }
            body.format-50x25_2up .barcode-label-pair:last-child, .format-50x25_2up .barcode-label-pair:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }
            body.format-50x25_2up .barcode-label-card, .format-50x25_2up .barcode-label-card {
                width: 49.0mm !important;
                height: 24.0mm !important;
                max-height: 24.0mm !important;
                margin: 0 !important;
                padding: 0.5mm 1mm !important;
                box-sizing: border-box !important;
                border: none !important;
                box-shadow: none !important;
                overflow: hidden !important;
            }

            /* 50x38mm 2-Up */
            body.format-50x38_2up, .format-50x38_2up html, .format-50x38_2up body {
                width: 102mm !important;
            }
            body.format-50x38_2up .barcode-label-pair, .format-50x38_2up .barcode-label-pair {
                width: 102mm !important;
                height: 38.0mm !important;
                max-height: 38.0mm !important;
                margin: 0 !important;
                padding: 0.5mm 1mm !important;
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }
            body.format-50x38_2up .barcode-label-pair:last-child, .format-50x38_2up .barcode-label-pair:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }
            body.format-50x38_2up .barcode-label-card, .format-50x38_2up .barcode-label-card {
                width: 49.0mm !important;
                height: 36.5mm !important;
                max-height: 36.5mm !important;
                margin: 0 !important;
                padding: 1mm 1.5mm !important;
                box-sizing: border-box !important;
                border: none !important;
                box-shadow: none !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
                text-align: center !important;
                overflow: hidden !important;
            }
            body.format-50x38_2up .label-barcode-svg, .format-50x38_2up .label-barcode-svg {
                max-width: 96% !important;
                max-height: 14mm !important;
                height: 13mm !important;
                margin: 0 auto !important;
                display: block !important;
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
    @if(in_array($format, ['a4', 'a4_24', 'a4_40']))
    <style>
        @page {
            size: A4 portrait;
            margin: 0mm !important;
        }
    </style>
    @elseif($format === '50x25_2up')
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
    @endif
</head>
<body class="format-{{ $format }}">

    <!-- Toolbar (Clean & Simple) -->
    <div class="print-toolbar d-flex justify-content-between align-items-center flex-wrap px-3 py-2">
        <div class="d-flex align-items-center my-1">
            @if(request()->filled('purchase_invoice_id'))
                <a href="{{ route('purchase.purchase-invoices.edit', request('purchase_invoice_id')) }}" class="btn btn-outline-secondary btn-sm mr-2 font-weight-bold">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Invoice
                </a>
            @else
                <button type="button" class="btn btn-outline-secondary btn-sm mr-2 font-weight-bold" onclick="if (window.opener) { window.close(); } else { window.location.href='{{ route('master.items.index') }}'; }">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </button>
            @endif
            <a href="{{ route('master.items.index') }}" class="btn btn-outline-primary btn-sm mr-2 font-weight-bold">
                <i class="fas fa-boxes mr-1"></i> Items Listing
            </a>
            <h5 class="mb-0 font-weight-bold text-dark mx-2">
                <i class="fas fa-barcode text-primary mr-1"></i> Barcode Stickers
            </h5>
            <span class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size: 13px;">
                Total: {{ count($labels) }} Stickers
            </span>
        </div>

        <div class="d-flex align-items-center my-1 flex-wrap">
            {{-- Big Clean 1-Click Print Button --}}
            <button type="button" class="btn btn-success btn-lg font-weight-bold shadow px-4 mr-2" id="btnDirectPrint" onclick="directPrintTsc()" title="Print labels directly to TSC TE244">
                <i class="fas fa-print mr-2"></i> Print Labels (TSC TE244)
            </button>

            {{-- Clean More Dropdown (for backup only) --}}
            <div class="dropdown">
                <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle font-weight-bold" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="More Options">
                    <i class="fas fa-ellipsis-v mr-1"></i> More
                </button>
                <div class="dropdown-menu dropdown-menu-right shadow">
                    <a class="dropdown-item font-weight-bold text-success" href="{{ route('printer-setup.download') }}" download="urbanpos_printer_setup.bat" title="Pehli baar is laptop par print karne ke liye ek baar run karein">
                        <i class="fas fa-tools mr-2 text-success"></i> <strong>Client PC Setup (Run Once)</strong>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item font-weight-bold" href="javascript:void(0)" onclick="downloadPrnFile()">
                        <i class="fas fa-file-download mr-2 text-primary"></i> Download .PRN File
                    </a>
                    <a class="dropdown-item font-weight-bold" href="javascript:void(0)" onclick="window.print()">
                        <i class="fas fa-print mr-2 text-secondary"></i> Browser Print Dialog
                    </a>
                </div>
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
                $isA4  = in_array($format, ['a4', 'a4_24', 'a4_40']);
                $is2Up = str_ends_with($format, '_2up');
                $opts  = $options ?? [
                    'show_store' => true,
                    'show_mrp'   => true,
                    'show_sell'  => true,
                    'show_exp'   => true,
                    'show_code'  => true,
                ];
            @endphp

            @if($isA4)
                {{-- A4 Laser/Inkjet Sheet: Chunk into exact pages (24-Up: 3x8 or 40-Up: 4x10) --}}
                @php
                    $pageSize = ($format === 'a4_40') ? 40 : 24;
                @endphp
                <div class="labels-container">
                    @foreach(array_chunk($labels, $pageSize) as $pageIndex => $sheetLabels)
                        <div class="a4-sheet-page">
                            @foreach($sheetLabels as $lbl)
                                <div class="barcode-label-card">
                                    @if($opts['show_store'])
                                        <div class="label-store-name">{{ $storeName }}</div>
                                    @endif
                                    <div class="label-item-name" title="{{ $lbl['name'] }}">
                                        {{ $lbl['name'] }}
                                        @if($opts['show_code'] && !empty($lbl['code']))
                                            <span style="font-size: 6pt; font-weight: 800; color: #475569; margin-left: 1mm;">#{{ $lbl['code'] }}</span>
                                        @endif
                                    </div>
                                    <div class="label-barcode-wrap">
                                        <svg class="label-barcode-svg" data-barcode="{{ $lbl['barcode'] }}"></svg>
                                    </div>
                                    <div class="label-prices">
                                        @if($opts['show_mrp'])
                                            @if($lbl['mrp'] > $lbl['sell_price'])
                                                <span class="label-mrp">MRP: ₹{{ number_format($lbl['mrp'], 2) }}</span>
                                            @else
                                                <span class="small font-weight-bold text-muted" style="font-size: 5pt;">INCL. TAX</span>
                                            @endif
                                        @endif
                                        @if($opts['show_sell'])
                                            <span class="label-sell">₹{{ number_format($lbl['sell_price'], 2) }}</span>
                                        @endif
                                        @if($opts['show_exp'] && !empty($lbl['exp_date']))
                                            <span class="label-exp">{{ substr($lbl['exp_date'], 5) }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                            {{-- Pad remainder of page with empty placeholder cards so Desmat sheet alignment is preserved --}}
                            @for($i = count($sheetLabels); $i < $pageSize; $i++)
                                <div class="barcode-label-card barcode-label-empty"></div>
                            @endfor
                        </div>
                    @endforeach
                </div>

            @elseif($is2Up)
                {{-- 2-Up Layout: 2 stickers paired side-by-side per row across thermal roll --}}
                <div class="labels-container">
                    @foreach(array_chunk($labels, 2) as $rawPair)
                        @php
                            $pair = (count($rawPair) === 1) ? [$rawPair[0], $rawPair[0]] : $rawPair;
                        @endphp
                        <div class="barcode-label-pair">
                            @foreach($pair as $lbl)
                                <div class="barcode-label-card">
                                    @if($opts['show_store'])
                                        <div class="label-store-name">{{ $storeName }}</div>
                                    @endif
                                    <div class="label-item-name" title="{{ $lbl['name'] }}">
                                        {{ $lbl['name'] }}
                                        @if($opts['show_code'] && !empty($lbl['code']))
                                            <span style="font-size: 6pt; font-weight: 800; color: #475569; margin-left: 1mm;">#{{ $lbl['code'] }}</span>
                                        @endif
                                    </div>
                                    <div class="label-barcode-wrap">
                                        <svg class="label-barcode-svg" data-barcode="{{ $lbl['barcode'] }}"></svg>
                                    </div>
                                    <div class="label-prices label-prices-flex">
                                        @php
                                            $mrpVal = (float)($lbl['mrp'] ?? 0);
                                            $sellVal = (float)($lbl['sell_price'] ?? 0);
                                            $mDec = ($mrpVal == (int)$mrpVal) ? 0 : 2;
                                            $sDec = ($sellVal == (int)$sellVal) ? 0 : 2;
                                        @endphp
                                        @if($opts['show_mrp'])
                                            @if($mrpVal > $sellVal)
                                                <span class="label-mrp">MRP: ₹{{ number_format($mrpVal, $mDec) }}</span>
                                            @else
                                                <span class="label-mrp-plain">MRP: ₹{{ number_format($mrpVal, $mDec) }}</span>
                                            @endif
                                        @endif
                                        @if($opts['show_sell'])
                                            <span class="label-sell">₹{{ number_format($sellVal, $sDec) }}</span>
                                        @endif
                                        @if($opts['show_exp'] && !empty($lbl['exp_date']))
                                            <span class="label-exp">{{ substr($lbl['exp_date'], 5) }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @else
                {{-- 1-Up Single Roll --}}
                <div class="labels-container">
                    @foreach ($labels as $lbl)
                        <div class="barcode-label-card">
                            @if($opts['show_store'])
                                <div class="label-store-name">{{ $storeName }}</div>
                            @endif
                            <div class="label-item-name" title="{{ $lbl['name'] }}">{{ $lbl['name'] }}</div>
                            @if($format === '102x64' && $opts['show_code'] && !empty($lbl['code']))
                                <div class="label-item-meta" style="font-size: 8pt; font-weight: 600; color: #334155; margin-bottom: 0.5mm;">
                                    <span>Code: <strong>{{ $lbl['code'] }}</strong></span>
                                    @if($opts['show_exp'] && !empty($lbl['exp_date']))
                                        <span class="ml-2">| &nbsp;Exp: <strong>{{ $lbl['exp_date'] }}</strong></span>
                                    @endif
                                </div>
                            @endif
                            <div class="label-barcode-wrap" style="display: flex; justify-content: center; align-items: center; margin: 0.5mm 0;">
                                <svg class="label-barcode-svg" data-barcode="{{ $lbl['barcode'] }}"></svg>
                            </div>
                            <div class="label-prices">
                                @if($opts['show_mrp'])
                                    @if($lbl['mrp'] > $lbl['sell_price'])
                                        <span class="label-mrp">MRP: ₹{{ number_format($lbl['mrp'], 2) }}</span>
                                    @else
                                        <span class="small font-weight-bold text-muted">M.R.P. Incl. of Taxes</span>
                                    @endif
                                @endif
                                @if($opts['show_sell'])
                                    <span class="label-sell">Price: ₹{{ number_format($lbl['sell_price'], 2) }}</span>
                                @endif
                                @if($format !== '102x64' && $opts['show_exp'] && !empty($lbl['exp_date']))
                                    <span class="small text-muted font-weight-bold">EXP: {{ $lbl['exp_date'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
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
            const isA4_24 = document.body.classList.contains('format-a4_24') || document.body.classList.contains('format-a4');
            const isA4_40 = document.body.classList.contains('format-a4_40');
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
                barWidth = isScaleXl ? 1.5 : (isScaleLarge ? 1.35 : 1.25);
                barHeight = isScaleXl ? 44 : (isScaleLarge ? 38 : 32);
                fontSize = isScaleXl ? 11.0 : (isScaleLarge ? 9.5 : 8.5);
            } else if (is50x50) {
                barWidth = isScaleXl ? 1.65 : 1.45;
                barHeight = isScaleXl ? 54 : 46;
                fontSize = 12;
            } else if (isA4_40) {
                barWidth = isScaleXl ? 1.25 : (isScaleLarge ? 1.15 : 1.0);
                barHeight = isScaleXl ? 22 : (isScaleLarge ? 19 : 16);
                fontSize = isScaleXl ? 8.5 : (isScaleLarge ? 7.5 : 7.0);
            } else if (isA4_24) {
                barWidth = isScaleXl ? 1.5 : (isScaleLarge ? 1.35 : 1.2);
                barHeight = isScaleXl ? 32 : (isScaleLarge ? 28 : 24);
                fontSize = isScaleXl ? 10.0 : (isScaleLarge ? 9.0 : 8.0);
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

        const BRIDGE_URL = 'http://127.0.0.1:8989';
        const EMBEDDED_TSPL = @json($tspl ?? '');

        function downloadPrnFile() {
            if (!EMBEDDED_TSPL || !EMBEDDED_TSPL.includes('PRINT')) {
                showNotification('error', 'No barcode data available to download.');
                return;
            }
            const blob = new Blob([EMBEDDED_TSPL], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'labels_tsc_te244.prn';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        function showNotification(type, message) {
            const alertClass = (type === 'success') ? 'alert-success border-success' : 'alert-danger border-danger';
            const icon = (type === 'success') ? 'fa-check-circle text-success' : 'fa-exclamation-circle text-danger';
            const html = `
                <div class="alert ${alertClass} alert-dismissible fade show shadow-lg position-fixed" style="top: 70px; right: 20px; z-index: 9999; min-width: 320px;" role="alert">
                    <i class="fas ${icon} mr-2"></i> <strong>${message}</strong>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            `;
            $('body').append(html);
            setTimeout(function () {
                $('.alert-dismissible').fadeOut(400, function () { $(this).remove(); });
            }, 4000);
        }

        async function checkBridgeStatus() {
            const $badge = $('#bridgeStatusBadge');
            const $dot = $('#bridgeStatusDot');
            const $text = $('#bridgeStatusText');

            @if(PHP_OS_FAMILY === 'Windows')
                $dot.removeClass('text-secondary text-danger text-warning').addClass('text-success');
                $text.html('<strong>TSC TE244 Ready</strong> (Direct USB001)');
                $badge.removeClass('border-danger text-danger').addClass('border-success text-success');
                return true;
            @endif

            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 1800);
                const res = await fetch(BRIDGE_URL + '/status', { method: 'GET', mode: 'cors', signal: controller.signal });
                clearTimeout(timeoutId);

                if (res.ok) {
                    const data = await res.json();
                    $dot.removeClass('text-secondary text-danger text-warning').addClass('text-success');
                    $text.html('<strong>TSC TE244 Online</strong> (1-Click Ready)');
                    $badge.removeClass('border-danger text-danger').addClass('border-success text-success');
                    return true;
                }
            } catch (e) {
                // Bridge not running
            }

            $dot.removeClass('text-secondary text-success').addClass('text-warning');
            $text.html('TSC Bridge Offline (Browser / .PRN Ready)');
            return false;
        }

        async function directPrintTsc() {
            const $btn = $('#btnDirectPrint');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Printing to TSC TE244...');

            try {
                if (!EMBEDDED_TSPL || !EMBEDDED_TSPL.includes('PRINT')) {
                    showNotification('error', 'No barcode items to print!');
                    return;
                }

                // 1. Try local HTTP Bridge first (instant 1-click print, no Save-As popup)
                try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 3500);
                    const printRes = await fetch(BRIDGE_URL + '/print', {
                        method: 'POST',
                        mode: 'cors',
                        targetAddressSpace: 'local',
                        signal: controller.signal,
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ tspl: EMBEDDED_TSPL })
                    });
                    clearTimeout(timeoutId);

                    if (printRes.ok) {
                        const result = await printRes.json();
                        if (result.success) {
                            showNotification('success', 'Printed to ' + (result.printer || 'TSC TE244') + '!');
                            return;
                        }
                    }
                } catch (bridgeErr) {
                    console.log('Local bridge not reachable, falling back to .prn download:', bridgeErr);
                }

                // 2. Fallback: download .prn file (picked up by background spooler)
                downloadPrnFile();
                showNotification('success', 'Print sent to TSC TE244!');
            } catch (err) {
                console.warn('Direct print error:', err);
                showNotification('error', 'Print error: ' + err.message);
            } finally {
                setTimeout(function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }, 400);
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            checkBridgeStatus();
            renderAllBarcodes();
        });

        $(document).ready(function () {
            @if(request()->boolean('autoprint') || request()->has('autoprint') || request()->has('direct'))
            setTimeout(function () {
                directPrintTsc();
            }, 450);
            @endif
        });
    </script>
</body>
</html>
