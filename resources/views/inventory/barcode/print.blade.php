<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Labels — UrbanPOS</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }

        /* Print controls — hidden in print mode */
        .no-print {
            background: #343a40;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .no-print button {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .no-print button:hover { background: #218838; }

        /* Label sheet */
        .label-sheet {
            display: flex;
            flex-wrap: wrap;
            padding: 10px;
            gap: 4px;
            background: #fff;
            width: fit-content;
            margin: 20px auto;
        }

        /* Individual label: 50mm × 30mm @ 96dpi */
        .label {
            width: 188px;   /* ≈ 50mm */
            height: 113px;  /* ≈ 30mm */
            border: 1px solid #ccc;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4px 6px;
            overflow: hidden;
            background: #fff;
            page-break-inside: avoid;
        }

        .label .item-name {
            font-size: 8pt;
            font-weight: bold;
            text-align: center;
            line-height: 1.2;
            max-height: 26px;
            overflow: hidden;
            width: 100%;
        }

        .label svg {
            max-width: 176px;
            max-height: 48px;
        }

        .label .price-row {
            display: flex;
            justify-content: space-between;
            width: 100%;
            font-size: 8pt;
            margin-top: 2px;
        }

        .label .price-row .mrp {
            font-weight: bold;
            font-size: 9pt;
        }

        .label .item-code {
            font-size: 7pt;
            color: #555;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .label-sheet { margin: 0; padding: 5mm; gap: 2mm; }
            .label {
                width: 50mm;
                height: 30mm;
                border: 0.5pt solid #999;
            }
            @page {
                size: A4;
                margin: 5mm;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <div>
            <strong>Barcode Labels</strong>
            <span style="margin-left:12px; color:#aaa;">{{ $labels->count() }} label(s) — 2 columns per row</span>
        </div>
        <button onclick="window.print()">🖨 Print Labels</button>
    </div>

    <div class="label-sheet" id="labelSheet">
        @foreach ($labels as $label)
        <div class="label">
            <div class="item-name">{{ $label->name }}</div>
            <svg class="barcode-{{ $loop->index }}" jsbarcode-value="{{ $label->barcode }}" jsbarcode-displayValue="true" jsbarcode-fontSize="8" jsbarcode-height="36" jsbarcode-margin="0"></svg>
            <div class="price-row">
                <span class="item-code">{{ $label->item_code }}</span>
                <span class="mrp">MRP: ₹{{ number_format($label->mrp, 2) }}</span>
            </div>
        </div>
        @endforeach
    </div>

    <script>
        // Render all barcodes
        document.querySelectorAll('[jsbarcode-value]').forEach(function(el) {
            try {
                JsBarcode(el, el.getAttribute('jsbarcode-value'), {
                    format: 'CODE128',
                    displayValue: true,
                    fontSize: 8,
                    height: 36,
                    margin: 0,
                    lineColor: '#000',
                });
            } catch (e) {
                // If barcode value is invalid, show a placeholder
                el.innerHTML = '<text x="50%" y="50%" text-anchor="middle" font-size="8">Invalid barcode</text>';
            }
        });

        // Auto-print after barcodes render
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 800);
        });
    </script>
</body>
</html>
