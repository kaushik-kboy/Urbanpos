<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barcode Label Printing</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 8px;
            background: #f4f4f4;
        }
        .no-print {
            padding: 10px;
            background: #333;
            color: #fff;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-print {
            background: #28a745;
            color: #fff;
            padding: 8px 18px;
            font-size: 14px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .label-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: flex-start;
        }

        /* Standard 50x25mm sticker */
        .label-card.standard {
            width: 50mm;
            height: 25mm;
            box-sizing: border-box;
            border: 1px dashed #ccc;
            padding: 2mm 3mm;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            text-align: center;
            page-break-inside: avoid;
        }

        /* Compact 38x25mm sticker */
        .label-card.compact {
            width: 38mm;
            height: 25mm;
            box-sizing: border-box;
            border: 1px dashed #ccc;
            padding: 2mm;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            text-align: center;
            page-break-inside: avoid;
        }

        /* Shelf tag 65x35mm */
        .label-card.shelf {
            width: 65mm;
            height: 35mm;
            box-sizing: border-box;
            border: 1px dashed #ccc;
            padding: 3mm;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            text-align: center;
            page-break-inside: avoid;
        }

        .store-name {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .item-name {
            font-size: 9px;
            font-weight: bold;
            line-height: 1.1;
            max-height: 20px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            width: 100%;
        }
        .barcode-svg {
            max-width: 100%;
            height: 32px !important;
        }
        .price-row {
            display: flex;
            justify-content: space-around;
            width: 100%;
            font-size: 9px;
            font-weight: bold;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .no-print {
                display: none;
            }
            .label-card {
                border: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div>
            <strong>Barcode Print Preview</strong> (Total: {{ count($labels) }} labels)
        </div>
        <div>
            <button class="btn-print" onclick="window.print()">Print Now</button>
            <button style="padding: 8px 15px; margin-left: 8px;" onclick="window.close()">Close</button>
        </div>
    </div>

    <div class="label-grid">
        @foreach ($labels as $idx => $lbl)
            <div class="label-card {{ $labelSize }}">
                <div class="store-name">{{ $lbl['store'] }}</div>
                <div class="item-name" title="{{ $lbl['name'] }}">{{ $lbl['name'] }}</div>
                <svg class="barcode-svg" id="barcode-{{ $idx }}"></svg>
                <div class="price-row">
                    @if ($lbl['mrp'] > 0)
                        <span>MRP: ₹{{ number_format($lbl['mrp'], 2) }}</span>
                    @endif
                    @if ($lbl['sell_price'] > 0 && $lbl['sell_price'] != $lbl['mrp'])
                        <span>OUR: ₹{{ number_format($lbl['sell_price'], 2) }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            @foreach ($labels as $idx => $lbl)
                try {
                    JsBarcode("#barcode-{{ $idx }}", "{{ $lbl['barcode'] }}", {
                        format: "CODE128",
                        height: 24,
                        width: 1.1,
                        fontSize: 8,
                        margin: 0,
                        displayValue: true
                    });
                } catch(e) {
                    console.error("Barcode render error:", e);
                }
            @endforeach
        });
    </script>
</body>
</html>
