@php
    $receiptSettings = \App\Models\ReceiptSetting::forDocument('stock_transfer', $stockTransfer->from_branch_id ?? null);
    $formatParam = request('format');
    $isA4 = ($formatParam === 'a4') || ($formatParam !== 'thermal' && $receiptSettings->isA4GstInvoice());

    $paperSize = $isA4 ? ($receiptSettings->paper_size === 'a5' ? 'a5' : 'a4') : ($receiptSettings->paper_size ?? '80mm');
    if ($isA4 && !in_array($paperSize, ['a4', 'a5'])) {
        $paperSize = 'a4';
    }
    $fontSize = $receiptSettings->font_size ?? 'normal';
    $containerWidth = match($paperSize) {
        '58mm'  => '58mm',
        '102mm' => '102mm',
        'a4'    => '100%',
        'a5'    => '100%',
        default => '80mm',
    };
    $containerMaxWidth = match($paperSize) {
        '58mm'  => '240px',
        '102mm' => '410px',
        'a4'    => '860px',
        'a5'    => '640px',
        default => '320px',
    };
    $baseFontSize = match($fontSize) {
        'small' => '10.5px',
        'large' => '13.5px',
        default => '12px',
    };
    $pageSizeRule = match($paperSize) {
        '58mm'  => '58mm auto',
        '102mm' => '102mm auto',
        'a4'    => 'A4 portrait',
        'a5'    => 'A5 portrait',
        default => '80mm auto',
    };
    $accentColor = $receiptSettings->getAccentColor();
    $docTitle = $receiptSettings->getDocumentTitle();
    $storeName = $receiptSettings->store_name ?: ($stockTransfer->fromBranch?->name ?: config('app.name', 'UrbanPOS'));
    $logoUrl = $receiptSettings->logo_path ? asset($receiptSettings->logo_path) : null;
    $showLogo = $receiptSettings->show_logo && !empty($logoUrl);
    $logoWidth = $receiptSettings->logo_width ?: 120;
    $hasReceivedQty = $stockTransfer->items->whereNotNull('received_qty')->isNotEmpty();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $docTitle }} - {{ $stockTransfer->transfer_number }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: @if($isA4) 'Segoe UI', Tahoma, Arial, sans-serif @else 'Courier New', Courier, monospace, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif @endif;
            background: #eef2f5;
            color: #1e293b;
            font-size: {{ $baseFontSize }};
            line-height: 1.35;
            padding: 20px 0;
        }

        .receipt-container {
            width: {{ $containerWidth }};
            max-width: {{ $containerMaxWidth }};
            margin: 0 auto;
            background: #fff;
            padding: @if($isA4) 20px 24px @else 12px 10px @endif;
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
            gap: 8px;
            flex-wrap: wrap;
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
        .btn-primary:hover { background: #0069d9; }
        .btn-secondary:hover { background: #5a6268; }

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

        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .screen-toolbar, .no-print {
                display: none !important;
            }
            .receipt-container {
                width: {{ in_array($paperSize, ['a4', 'a5']) ? '100%' : $containerWidth }} !important;
                max-width: {{ $containerMaxWidth }} !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                padding: @if($isA4) 0 @else 4px 6px @endif !important;
                margin: 0 auto !important;
            }
            @page {
                size: {{ $pageSizeRule }};
                margin: @if($isA4) 8mm 8mm 8mm 8mm @else 0mm 2mm @endif;
            }
        }
        {!! $receiptSettings->custom_css ?? '' !!}
    </style>
</head>
<body>

    {{-- Screen Toolbar --}}
    <div class="screen-toolbar">
        <div>
            <a href="{{ route('inventory.stock-transfers.show', $stockTransfer) }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back to Transfer
            </a>
            @if($isA4)
                <a href="{{ request()->fullUrlWithQuery(['format' => 'thermal']) }}" class="btn btn-secondary" style="background: #374151;" title="Switch to Thermal Slip format">
                    <i class="fas fa-receipt" style="margin-right: 5px;"></i> Thermal Slip
                </a>
            @else
                <a href="{{ request()->fullUrlWithQuery(['format' => 'a4']) }}" class="btn btn-secondary" style="background: #374151;" title="Switch to A4 format">
                    <i class="fas fa-file-invoice" style="margin-right: 5px;"></i> A4 Sheet
                </a>
            @endif
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print" style="margin-right: 5px;"></i> {{ $isA4 ? 'Print Transfer Note' : 'Print Slip' }}
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- A4 / A5 GST FORMAT                                                  --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @if ($isA4)
        <div class="receipt-container" style="--accent: {{ $accentColor }};">
            {{-- Header Layout Handling --}}
            @php $layout = $receiptSettings->getHeaderLayout(); @endphp

            @if ($layout === 'centered')
                <div style="text-align: center; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 2px solid {{ $accentColor }};">
                    @if($showLogo)
                        <img src="{{ $logoUrl }}" alt="Logo" style="width: {{ $logoWidth }}px; max-width: 100%; height: auto; margin-bottom: 4px;"><br>
                    @endif
                    <div style="font-size: 20px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; color: {{ $accentColor }};">
                        {{ $storeName }}
                    </div>
                    @if($receiptSettings->tagline)
                        <div style="font-size: 10px; font-weight: 600; color: #475569;">{{ $receiptSettings->tagline }}</div>
                    @endif
                    <div style="font-size: 11px; color: #334155; margin-top: 3px;">
                        {!! nl2br(e($receiptSettings->header_address ?: $stockTransfer->fromBranch?->address)) !!}
                    </div>
                    <div style="font-size: 11px; color: #334155; margin-top: 2px;">
                        @if($receiptSettings->phone || $stockTransfer->fromBranch?->phone)<strong>Phone:</strong> {{ $receiptSettings->phone ?: $stockTransfer->fromBranch?->phone }}@endif
                        @if($receiptSettings->phone_alt) / {{ $receiptSettings->phone_alt }}@endif
                        @if($receiptSettings->email) | <strong>Email:</strong> {{ $receiptSettings->email }}@endif
                        @if($receiptSettings->gstin || $stockTransfer->fromBranch?->gst_number) | <strong>GSTIN:</strong> {{ $receiptSettings->gstin ?: $stockTransfer->fromBranch?->gst_number }}@endif
                    </div>
                    <div style="display: inline-block; background: {{ $accentColor }}; color: #fff; font-weight: 800; font-size: 12px; letter-spacing: 1.5px; padding: 4px 24px; border-radius: 4px; margin-top: 8px; text-transform: uppercase;">
                        {{ $docTitle }}
                    </div>
                </div>

            @elseif ($layout === 'logo_left_address_right')
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                    <div style="width: 48%;">
                        @if($showLogo)
                            <img src="{{ $logoUrl }}" alt="Logo" style="width: {{ $logoWidth }}px; max-width: 100%; height: auto; margin-bottom: 4px;"><br>
                        @endif
                        <div style="font-size: 19px; font-weight: 900; color: {{ $accentColor }}; text-transform: uppercase;">
                            {{ $storeName }}
                        </div>
                        @if($receiptSettings->tagline)
                            <div style="font-size: 10px; color: #64748b; font-weight: bold;">{{ $receiptSettings->tagline }}</div>
                        @endif
                    </div>
                    <div style="width: 50%; text-align: right; font-size: 10.5px; color: #334155; line-height: 1.4;">
                        <div style="font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase;">{{ $receiptSettings->getHeaderOfficeTitle() }}</div>
                        <div style="font-weight: bold; font-size: 12px; color: #0f172a;">{{ $storeName }}</div>
                        <div>{!! nl2br(e($receiptSettings->header_address ?: $stockTransfer->fromBranch?->address)) !!}</div>
                        @if($receiptSettings->phone || $stockTransfer->fromBranch?->phone)<div><strong>Phone:</strong> {{ $receiptSettings->phone ?: $stockTransfer->fromBranch?->phone }}</div>@endif
                        @if($receiptSettings->gstin || $stockTransfer->fromBranch?->gst_number)<div><strong>GSTIN:</strong> {{ $receiptSettings->gstin ?: $stockTransfer->fromBranch?->gst_number }}</div>@endif
                    </div>
                </div>
                <div style="background-color: {{ $accentColor }}; color: #fff; display: flex; justify-content: space-between; align-items: center; padding: 5px 12px; border-radius: 3px; font-weight: bold; margin-bottom: 12px;">
                    <span style="font-size: 13px; letter-spacing: 1px; text-transform: uppercase;">{{ $docTitle }}</span>
                    <span style="font-size: 10.5px; opacity: 0.9;">Transfer Note</span>
                </div>

            @elseif ($layout === 'logo_right_address_left')
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                    <div style="width: 52%; font-size: 10.5px; color: #334155; line-height: 1.4;">
                        <div style="font-weight: 900; font-size: 18px; color: {{ $accentColor }}; text-transform: uppercase;">{{ $storeName }}</div>
                        @if($receiptSettings->tagline)<div style="font-size: 10px; color: #64748b; font-weight: bold; margin-bottom: 2px;">{{ $receiptSettings->tagline }}</div>@endif
                        <div>{!! nl2br(e($receiptSettings->header_address ?: $stockTransfer->fromBranch?->address)) !!}</div>
                        @if($receiptSettings->phone || $stockTransfer->fromBranch?->phone)<div><strong>Phone:</strong> {{ $receiptSettings->phone ?: $stockTransfer->fromBranch?->phone }}</div>@endif
                        @if($receiptSettings->gstin || $stockTransfer->fromBranch?->gst_number)<div><strong>GSTIN:</strong> {{ $receiptSettings->gstin ?: $stockTransfer->fromBranch?->gst_number }}</div>@endif
                    </div>
                    <div style="width: 45%; text-align: right;">
                        @if($showLogo)
                            <img src="{{ $logoUrl }}" alt="Logo" style="width: {{ $logoWidth }}px; max-width: 100%; height: auto; margin-bottom: 4px;">
                        @endif
                    </div>
                </div>
                <div style="background-color: {{ $accentColor }}; color: #fff; display: flex; justify-content: space-between; align-items: center; padding: 5px 12px; border-radius: 3px; font-weight: bold; margin-bottom: 12px;">
                    <span style="font-size: 13px; letter-spacing: 1px; text-transform: uppercase;">{{ $docTitle }}</span>
                    <span style="font-size: 10.5px; opacity: 0.9;">Transfer Note</span>
                </div>

            @else
                {{-- Default: Logo Left, Details Right --}}
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; border-bottom: 2px solid {{ $accentColor }}; padding-bottom: 10px;">
                    <div style="width: 55%;">
                        @if($showLogo)
                            <img src="{{ $logoUrl }}" alt="Logo" style="width: {{ $logoWidth }}px; max-width: 100%; height: auto; margin-bottom: 6px;"><br>
                        @endif
                        <div style="font-size: 18px; font-weight: 800; color: {{ $accentColor }}; text-transform: uppercase;">
                            {{ $storeName }}
                        </div>
                        @if($receiptSettings->tagline)
                            <div style="font-size: 10px; font-weight: bold; color: #64748b; margin-bottom: 3px;">{{ $receiptSettings->tagline }}</div>
                        @endif
                        <div style="font-size: 10.5px; color: #334155; line-height: 1.35; margin-top: 3px;">
                            <div>{!! nl2br(e($receiptSettings->header_address ?: $stockTransfer->fromBranch?->address)) !!}</div>
                            @if($receiptSettings->phone || $stockTransfer->fromBranch?->phone)<div><strong>Phone:</strong> {{ $receiptSettings->phone ?: $stockTransfer->fromBranch?->phone }}</div>@endif
                            @if($receiptSettings->gstin || $stockTransfer->fromBranch?->gst_number)<div><strong>GSTIN:</strong> {{ $receiptSettings->gstin ?: $stockTransfer->fromBranch?->gst_number }}</div>@endif
                        </div>
                    </div>
                    <div style="width: 42%; text-align: right;">
                        <div style="display: inline-block; background: {{ $accentColor }}; color: #fff; padding: 4px 14px; font-weight: 800; font-size: 12px; letter-spacing: 1px; border-radius: 3px; text-transform: uppercase; margin-bottom: 8px;">
                            {{ $docTitle }}
                        </div>
                        <div style="font-size: 12px; font-weight: bold; color: #0f172a;">Transfer #: {{ $stockTransfer->transfer_number }}</div>
                        <div style="font-size: 11px; color: #334155;">Date: <strong>{{ $stockTransfer->transfer_date->format('d-m-Y') }}</strong></div>
                        <div style="font-size: 11px; color: #334155;">Status: <strong style="color: {{ $stockTransfer->status === 'Received' ? '#16a34a' : '#d97706' }};">{{ $stockTransfer->status }}</strong></div>
                    </div>
                </div>
            @endif

            {{-- From Branch & To Branch Boxes --}}
            <div style="display: flex; gap: 12px; margin-bottom: 14px;">
                <div style="flex: 1; border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 12px; background: #f8fafc;">
                    <div style="font-weight: 700; font-size: 11px; color: {{ $accentColor }}; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 5px; text-transform: uppercase;">
                        From Branch (Dispatched):
                    </div>
                    <div style="font-size: 11px; line-height: 1.4;">
                        <div class="font-bold" style="font-size: 12px; color: #0f172a;">{{ $stockTransfer->fromBranch?->name }}</div>
                        <div>{{ $stockTransfer->fromBranch?->address ?? '' }}</div>
                        @if($stockTransfer->fromBranch?->phone)<div>Phone: {{ $stockTransfer->fromBranch->phone }}</div>@endif
                        @if($stockTransfer->fromBranch?->gst_number)<div>GSTIN: <strong>{{ $stockTransfer->fromBranch->gst_number }}</strong></div>@endif
                    </div>
                </div>
                <div style="flex: 1; border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 12px; background: #f8fafc;">
                    <div style="font-weight: 700; font-size: 11px; color: {{ $accentColor }}; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 5px; text-transform: uppercase;">
                        To Branch (Destination):
                    </div>
                    <div style="font-size: 11px; line-height: 1.4;">
                        <div class="font-bold" style="font-size: 12px; color: #0f172a;">{{ $stockTransfer->toBranch?->name }}</div>
                        <div>{{ $stockTransfer->toBranch?->address ?? '' }}</div>
                        @if($stockTransfer->toBranch?->phone)<div>Phone: {{ $stockTransfer->toBranch->phone }}</div>@endif
                        @if($stockTransfer->toBranch?->gst_number)<div>GSTIN: <strong>{{ $stockTransfer->toBranch->gst_number }}</strong></div>@endif
                    </div>
                </div>
            </div>

            {{-- Items Table --}}
            <table style="width: 100%; border-collapse: collapse; font-size: 11px; border: 1px solid #cbd5e1; margin-bottom: 14px;">
                <thead>
                    <tr style="background-color: {{ $accentColor }}; color: #ffffff;">
                        <th style="padding: 6px 8px; border: 1px solid #cbd5e1; width: 35px; text-align: center;">#</th>
                        @if($receiptSettings->show_item_code ?? true)
                            <th style="padding: 6px 8px; border: 1px solid #cbd5e1; width: 95px; text-align: left;">Item Code</th>
                        @endif
                        <th style="padding: 6px 8px; border: 1px solid #cbd5e1; text-align: left;">Item Description</th>
                        <th style="padding: 6px 8px; border: 1px solid #cbd5e1; width: 85px; text-align: center;">Exp Date</th>
                        <th style="padding: 6px 8px; border: 1px solid #cbd5e1; width: 75px; text-align: right;">Dispatched</th>
                        @if($hasReceivedQty)
                            <th style="padding: 6px 8px; border: 1px solid #cbd5e1; width: 75px; text-align: right;">Received</th>
                        @endif
                        @if($receiptSettings->show_mrp ?? true)
                            <th style="padding: 6px 8px; border: 1px solid #cbd5e1; width: 75px; text-align: right;">MRP</th>
                        @endif
                        <th style="padding: 6px 8px; border: 1px solid #cbd5e1; width: 85px; text-align: right;">Unit Cost</th>
                        <th style="padding: 6px 8px; border: 1px solid #cbd5e1; width: 95px; text-align: right;">Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stockTransfer->items as $idx => $line)
                        @php
                            $unitCost = (float) $line->unit_cost;
                            if ($unitCost <= 0) {
                                $unitCost = (float) ($line->item?->cost_price ?: ($line->item?->landing_cost ?: ($line->item?->purchase_rate ?: ($line->item?->sell_price ?: 0))));
                            }
                            $lineTotal = (float) $line->qty * $unitCost;

                            $expDate = $line->exp_date;
                            if (empty($expDate) && $line->batch_no && $line->item_id) {
                                $expDateVal = \Illuminate\Support\Facades\DB::table('stock_ledger')
                                    ->where('item_id', $line->item_id)
                                    ->where('batch_no', $line->batch_no)
                                    ->whereNotNull('exp_date')
                                    ->whereNotIn('exp_date', ['', '0000-00-00'])
                                    ->orderByDesc('id')
                                    ->value('exp_date');
                                if (empty($expDateVal)) {
                                    $expDateVal = \Illuminate\Support\Facades\DB::table('purchase_invoice_items')
                                        ->where('item_id', $line->item_id)
                                        ->where('batch_no', $line->batch_no)
                                        ->whereNotNull('exp_date')
                                        ->whereNotIn('exp_date', ['', '0000-00-00'])
                                        ->orderByDesc('id')
                                        ->value('exp_date');
                                }
                                if (!empty($expDateVal)) {
                                    try {
                                        $expDate = \Carbon\Carbon::parse($expDateVal);
                                    } catch (\Throwable) {}
                                }
                            }
                            if (empty($expDate) && $line->item_id) {
                                $itemExp = \Illuminate\Support\Facades\DB::table('purchase_invoice_items')
                                    ->where('item_id', $line->item_id)
                                    ->whereNotNull('exp_date')
                                    ->whereNotIn('exp_date', ['', '0000-00-00'])
                                    ->orderByDesc('id')
                                    ->value('exp_date');
                                if (empty($itemExp)) {
                                    $itemExp = \Illuminate\Support\Facades\DB::table('stock_ledger')
                                        ->where('item_id', $line->item_id)
                                        ->whereNotNull('exp_date')
                                        ->whereNotIn('exp_date', ['', '0000-00-00'])
                                        ->orderByDesc('id')
                                        ->value('exp_date');
                                }
                                if (!empty($itemExp)) {
                                    try {
                                        $expDate = \Carbon\Carbon::parse($itemExp);
                                    } catch (\Throwable) {}
                                }
                            }
                        @endphp
                        <tr style="{{ $idx % 2 === 1 ? 'background-color: #f8fafc;' : '' }}">
                            <td style="padding: 6px 8px; border: 1px solid #cbd5e1; text-align: center;">{{ $idx + 1 }}</td>
                            @if($receiptSettings->show_item_code ?? true)
                                <td style="padding: 6px 8px; border: 1px solid #cbd5e1;">{{ $line->item?->item_code ?? $line->item?->ean_upc_code ?? '—' }}</td>
                            @endif
                            <td style="padding: 6px 8px; border: 1px solid #cbd5e1; font-weight: 600;">
                                {{ $line->item?->name }}
                                @if($line->batch_no)
                                    <span style="font-size: 10px; color: #64748b; font-weight: normal;">(Batch: {{ $line->batch_no }})</span>
                                @endif
                            </td>
                            <td style="padding: 6px 8px; border: 1px solid #cbd5e1; text-align: center;">{{ $expDate ? $expDate->format('d-m-Y') : '—' }}</td>
                            <td style="padding: 6px 8px; border: 1px solid #cbd5e1; text-align: right; font-weight: bold;">{{ number_format($line->qty, 0) }}</td>
                            @if($hasReceivedQty)
                                <td style="padding: 6px 8px; border: 1px solid #cbd5e1; text-align: right;">{{ $line->received_qty !== null ? number_format($line->received_qty, 0) : '—' }}</td>
                            @endif
                            @if($receiptSettings->show_mrp ?? true)
                                <td style="padding: 6px 8px; border: 1px solid #cbd5e1; text-align: right;">₹{{ number_format((float) ($line->mrp ?: ($line->item?->mrp ?: 0)), 2) }}</td>
                            @endif
                            <td style="padding: 6px 8px; border: 1px solid #cbd5e1; text-align: right;">₹{{ number_format($unitCost, 2) }}</td>
                            <td style="padding: 6px 8px; border: 1px solid #cbd5e1; text-align: right; font-weight: bold;">₹{{ number_format($lineTotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    @php
                        $colsBeforeQty = 2; // # and Item Description
                        if ($receiptSettings->show_item_code ?? true) $colsBeforeQty++;
                    @endphp
                    <tr style="background-color: #f1f5f9; font-weight: bold; border-top: 2px solid #cbd5e1;">
                        <td colspan="{{ $colsBeforeQty }}" style="padding: 7px 8px; border: 1px solid #cbd5e1; text-align: right;">Total:</td>
                        <td style="padding: 7px 8px; border: 1px solid #cbd5e1; text-align: right;">{{ number_format($stockTransfer->items->sum('qty'), 0) }}</td>
                        @if($hasReceivedQty)
                            <td style="padding: 7px 8px; border: 1px solid #cbd5e1; text-align: right;">{{ $stockTransfer->items->whereNotNull('received_qty')->isNotEmpty() ? number_format($stockTransfer->items->sum('received_qty'), 0) : '—' }}</td>
                        @endif
                        @if($receiptSettings->show_mrp ?? true)
                            <td style="padding: 7px 8px; border: 1px solid #cbd5e1;"></td>
                        @endif
                        <td style="padding: 7px 8px; border: 1px solid #cbd5e1;"></td>
                        <td style="padding: 7px 8px; border: 1px solid #cbd5e1; text-align: right;">₹{{ number_format($stockTransfer->total_value, 2) }}</td>
                    </tr>
                </tfoot>
            </table>

            @if($stockTransfer->remarks)
                <div style="margin-top: 10px; border: 1px dashed #94a3b8; background: #f8fafc; padding: 8px 12px; font-size: 11px; border-radius: 4px;">
                    <strong>Remarks:</strong> {{ $stockTransfer->remarks }}
                </div>
            @endif

            @if($receiptSettings->show_signature_box ?? true)
                <div style="margin-top: 45px; display: flex; justify-content: space-between; padding: 0 20px;">
                    <div style="width: 220px; border-top: 1px solid #334155; text-align: center; padding-top: 6px; font-size: 11px; font-weight: 600;">
                        Dispatched By (From Branch)
                    </div>
                    <div style="width: 220px; border-top: 1px solid #334155; text-align: center; padding-top: 6px; font-size: 11px; font-weight: 600;">
                        Received By (To Branch)
                    </div>
                </div>
            @endif

            @if($receiptSettings->footer_policy || $receiptSettings->footer_note)
                <div style="margin-top: 25px; font-size: 10.5px; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 10px; text-align: center;">
                    @if($receiptSettings->footer_policy)
                        <div style="white-space: pre-line; margin-bottom: 4px;">{!! nl2br(e($receiptSettings->footer_policy)) !!}</div>
                    @endif
                    @if($receiptSettings->footer_note)
                        <div style="font-weight: bold; white-space: pre-line;">{!! nl2br(e($receiptSettings->footer_note)) !!}</div>
                    @endif
                </div>
            @endif

            @if($receiptSettings->show_barcode ?? true)
                <div style="text-align: center; margin-top: 20px;">
                    <div style="display: inline-block; height: 32px; width: 180px; background: repeating-linear-gradient(90deg, #000 0px, #000 2px, #fff 2px, #fff 4px, #000 4px, #000 5px, #fff 5px, #fff 8px);"></div>
                    <div style="font-size: 10px; font-weight: bold; letter-spacing: 1px; margin-top: 2px;">* {{ $stockTransfer->transfer_number }} *</div>
                </div>
            @endif
        </div>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- THERMAL SLIP FORMAT (58mm / 80mm / 102mm)                          --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @else
        <div class="receipt-container">
            @if($showLogo)
                <div class="text-center" style="margin-bottom: 6px;">
                    <img src="{{ $logoUrl }}" alt="Store Logo" style="width: {{ $logoWidth }}px; max-width: 100%; height: auto;">
                </div>
            @endif

            <div class="text-center">
                <div class="font-bold text-uppercase" style="font-size: 15px; letter-spacing: 0.5px;">
                    {{ $storeName }}
                </div>
                @if($receiptSettings->tagline)
                    <div style="font-size: 10.5px; font-weight: bold; color: #444;">{{ $receiptSettings->tagline }}</div>
                @endif
                <div style="font-size: 10.5px; line-height: 1.25; margin-top: 2px;">
                    {!! nl2br(e($receiptSettings->header_address ?: $stockTransfer->fromBranch?->address)) !!}
                </div>
                @if($receiptSettings->phone || $stockTransfer->fromBranch?->phone)
                    <div style="font-size: 10.5px;">Tel: {{ $receiptSettings->phone ?: $stockTransfer->fromBranch?->phone }}@if($receiptSettings->phone_alt) / {{ $receiptSettings->phone_alt }}@endif</div>
                @endif
                @if($receiptSettings->gstin || $stockTransfer->fromBranch?->gst_number)
                    <div style="font-size: 10.5px; font-weight: bold;">GSTIN: {{ $receiptSettings->gstin ?: $stockTransfer->fromBranch?->gst_number }}</div>
                @endif
            </div>

            <div class="divider"></div>

            <div class="text-center font-bold text-uppercase" style="font-size: 12px; letter-spacing: 1px;">
                {{ $docTitle }}
            </div>

            <div class="divider"></div>

            {{-- Meta info --}}
            <table style="width: 100%; font-size: 11px; line-height: 1.35;">
                <tr>
                    <td class="font-bold" style="width: 55%;">Transfer #: {{ $stockTransfer->transfer_number }}</td>
                    <td class="text-right" style="width: 45%;">Date: {{ $stockTransfer->transfer_date->format('d-m-Y') }}</td>
                </tr>
                <tr>
                    <td colspan="2">From: <strong>{{ $stockTransfer->fromBranch?->name }}</strong></td>
                </tr>
                <tr>
                    <td colspan="2">To: <strong>{{ $stockTransfer->toBranch?->name }}</strong></td>
                </tr>
                <tr>
                    <td>Status: <strong>{{ $stockTransfer->status }}</strong></td>
                    <td class="text-right">Items: {{ $stockTransfer->items->count() }}</td>
                </tr>
            </table>

            <div class="divider"></div>

            {{-- Items --}}
            <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                <thead>
                    <tr style="border-bottom: 1px dashed #000;">
                        <th style="text-align: left; padding: 3px 0;">Item Description</th>
                        <th style="text-align: right; padding: 3px 0; width: 45px;">Qty</th>
                        <th style="text-align: right; padding: 3px 0; width: 55px;">Cost</th>
                        <th style="text-align: right; padding: 3px 0; width: 60px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stockTransfer->items as $idx => $line)
                        @php
                            $unitCost = (float) $line->unit_cost;
                            if ($unitCost <= 0) {
                                $unitCost = (float) ($line->item?->cost_price ?: ($line->item?->landing_cost ?: ($line->item?->purchase_rate ?: ($line->item?->sell_price ?: 0))));
                            }
                            $lineTotal = (float) $line->qty * $unitCost;
                        @endphp
                        <tr>
                            <td colspan="4" style="font-weight: bold; padding-top: 4px;">
                                {{ $idx + 1 }}. {{ $line->item?->name }}
                            </td>
                        </tr>
                        <tr>
                            <td style="color: #444; font-size: 10px; padding-bottom: 3px;">
                                @if($receiptSettings->show_item_code ?? true)[{{ $line->item?->item_code ?? $line->item?->ean_upc_code ?? '—' }}]@endif
                                @if($line->batch_no) B:{{ $line->batch_no }}@endif
                                @if($receiptSettings->show_mrp ?? true)
                                    MRP: ₹{{ number_format((float) ($line->mrp ?: ($line->item?->mrp ?: 0)), 2) }}
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: bold; vertical-align: top;">{{ number_format($line->qty, 0) }}</td>
                            <td style="text-align: right; vertical-align: top;">₹{{ number_format($unitCost, 2) }}</td>
                            <td style="text-align: right; font-weight: bold; vertical-align: top;">₹{{ number_format($lineTotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="divider"></div>

            <table style="width: 100%; font-size: 11.5px; line-height: 1.4;">
                <tr>
                    <td>Total Transfer Qty:</td>
                    <td class="text-right font-bold">{{ number_format($stockTransfer->items->sum('qty'), 0) }}</td>
                </tr>
            </table>

            <div class="double-divider"></div>

            <table style="width: 100%; font-size: 13.5px; font-weight: 900;">
                <tr>
                    <td>TOTAL TRANSFER VALUE:</td>
                    <td class="text-right">₹{{ number_format($stockTransfer->total_value, 2) }}</td>
                </tr>
            </table>

            <div class="double-divider"></div>

            @if($stockTransfer->remarks)
                <div style="margin-top: 8px; font-size: 10.5px;">
                    <strong>Remarks:</strong> {{ $stockTransfer->remarks }}
                </div>
                <div class="divider"></div>
            @endif

            @if($receiptSettings->show_signature_box ?? true)
                <div style="margin-top: 25px; font-size: 10px; display: flex; justify-content: space-between;">
                    <div style="width: 48%; border-top: 1px solid #333; text-align: center; padding-top: 4px;">
                        Dispatched By
                    </div>
                    <div style="width: 48%; border-top: 1px solid #333; text-align: center; padding-top: 4px;">
                        Received By
                    </div>
                </div>
            @endif

            @if($receiptSettings->footer_policy || $receiptSettings->footer_note)
                <div style="margin-top: 15px; font-size: 10px; text-align: center; color: #333;">
                    @if($receiptSettings->footer_policy)
                        <div style="white-space: pre-line; margin-bottom: 3px;">{!! nl2br(e($receiptSettings->footer_policy)) !!}</div>
                    @endif
                    @if($receiptSettings->footer_note)
                        <div style="font-weight: bold; white-space: pre-line;">{!! nl2br(e($receiptSettings->footer_note)) !!}</div>
                    @endif
                </div>
            @endif

            @if($receiptSettings->show_barcode ?? true)
                <div style="text-align: center; margin-top: 15px;">
                    <div style="display: inline-block; height: 28px; width: 160px; background: repeating-linear-gradient(90deg, #000 0px, #000 2px, #fff 2px, #fff 4px, #000 4px, #000 5px, #fff 5px, #fff 8px);"></div>
                    <div style="font-size: 9.5px; font-weight: bold; letter-spacing: 1px; margin-top: 2px;">* {{ $stockTransfer->transfer_number }} *</div>
                </div>
            @endif
        </div>
    @endif

</body>
</html>
