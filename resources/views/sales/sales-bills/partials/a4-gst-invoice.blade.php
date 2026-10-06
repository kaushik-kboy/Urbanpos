@php
    $receiptSettings = $receiptSettings ?? \App\Models\ReceiptSetting::forDocument('sales_bill', $salesBill->branch_id ?? null);
    $accentColor = $receiptSettings->getAccentColor();
    $headerLayout = $receiptSettings->getHeaderLayout();
    $storeName = $receiptSettings->store_name ?: ($salesBill->branch?->name ?: 'Urban Pets');
    $tagline = $receiptSettings->tagline ?: 'Pet Care For A Happier Tomorrow';
    $phone = $receiptSettings->phone ?: ($salesBill->branch?->phone ?: '9662825744');
    $email = $receiptSettings->email ?: ($salesBill->branch?->email ?: 'support@urbanpets.in');
    $gstin = $receiptSettings->gstin ?: ($salesBill->branch?->gst_number ?: '24ABCDE1234F1Z5');
    $address = $receiptSettings->header_address ?: ($salesBill->branch?->address ?: 'Shop No. 1, Ahmedabad, Gujarat - 380015');
    $logoUrl = $receiptSettings->logo_path ?: ($salesBill->branch?->logo_url ?? null);
    $logoWidth = $receiptSettings->logo_width ?: 120;
    $showLogo = $receiptSettings->show_logo && !empty($logoUrl);

    $customer = $salesBill->customer;
    $customerName = $customer?->name ?: 'Walk-in Customer';
    $customerMobile = $customer?->mobile ?: ($customer?->phone ?: '');
    $customerAddress = $customer?->address ?: ($customer?->city ? "{$customer->city}, {$customer->state}" : 'Satellite, Ahmedabad, Gujarat - 380015');
    $customerGstin = trim((string) ($salesBill->customer_gstin ?: ($customer?->gst_no ?: '')));
    $hasValidGstin = !empty($customerGstin) && strcasecmp($customerGstin, 'unregistered') !== 0;

    $pet = $salesBill->pet ?: $customer?->pets?->first();
    $petName = $pet?->name;

    $customerGstType = $customer?->gst_type
        ?: ($hasValidGstin ? 'Registered (B2B)' : 'Unregistered (Consumer)');
    $headerOfficeTitle = $receiptSettings->getHeaderOfficeTitle();

    $isInterstate = !empty($salesBill->total_igst) && (float) $salesBill->total_igst > 0;
    $totalDiscount = (float) ($salesBill->disc_amount ?? 0);
    $totalBeforeDisc = (float) $salesBill->items->sum(fn ($i) => ($i->qty * ($i->mrp ?: $i->sell_price)));
    if ($totalBeforeDisc <= 0) {
        $totalBeforeDisc = (float) $salesBill->total + $totalDiscount;
    }
    $taxableTotal = (float) $salesBill->items->sum(fn ($i) => ($i->net_amount - ($i->gst_tax_amount ?? 0)));
    if ($taxableTotal <= 0) {
        $taxableTotal = max(0, (float) $salesBill->total - (float) ($salesBill->total_gst ?? 0));
    }
    $totalQty = (float) $salesBill->items->sum('qty');
    $totalItems = $salesBill->items->count();

    // Tax Breakup by GST rate
    $taxSummary = [];
    foreach ($salesBill->items as $item) {
        $rate = (float) ($item->gst_percent ?? ($item->item?->gstTax?->rate ?? 18));
        $rateKey = number_format($rate, 0) . '%';
        if (!isset($taxSummary[$rateKey])) {
            $taxSummary[$rateKey] = [
                'rate' => $rate,
                'rate_label' => $rateKey,
                'taxable' => 0.0,
                'cgst' => 0.0,
                'sgst' => 0.0,
                'igst' => 0.0,
                'cess' => 0.0,
                'total_tax' => 0.0,
            ];
        }
        $lineTaxable = (float) ($item->net_amount - ($item->gst_tax_amount ?? 0));
        $lineTax = (float) ($item->gst_tax_amount ?? 0);
        $taxSummary[$rateKey]['taxable'] += $lineTaxable;
        $taxSummary[$rateKey]['total_tax'] += $lineTax;
        if ($isInterstate) {
            $taxSummary[$rateKey]['igst'] += $lineTax;
        } else {
            $taxSummary[$rateKey]['cgst'] += $lineTax / 2;
            $taxSummary[$rateKey]['sgst'] += $lineTax / 2;
        }
    }
    ksort($taxSummary);

    $amountInWords = \App\Services\Accounting\NumberToWords::toIndianCurrency($salesBill->total);

    // Payment details
    $payments = $salesBill->relationLoaded('payments') ? $salesBill->payments : $salesBill->payments()->with('tenderType')->get();
    $paymentModeStr = $payments->isNotEmpty()
        ? $payments->map(fn ($p) => $p->tenderType?->name ?: 'Payment')->filter()->unique()->implode(', ')
        : ($salesBill->payment_type && $salesBill->payment_type !== 'None' ? $salesBill->payment_type : 'Cash');
    $paymentRefStr = $salesBill->posting_key ?: ($salesBill->bill_number ?: 'REF-' . $salesBill->id);
@endphp

<div class="a4-gst-invoice-root" style="--accent: {{ $accentColor }}; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; color: #1e293b; line-height: 1.35; font-size: 11px;">
    <style>
        .a4-gst-invoice-root {
            background: #fff;
            max-width: 820px;
            margin: 0 auto;
            padding: 16px 20px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
            border-radius: 4px;
        }
        .a4-gst-invoice-root table {
            border-collapse: collapse;
            width: 100%;
        }
        .a4-gst-invoice-root th, .a4-gst-invoice-root td {
            padding: 4px 6px;
        }
        .a4-gst-invoice-root .border-box {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
        }
        .a4-gst-invoice-root .accent-bg {
            background-color: var(--accent);
            color: #ffffff;
        }
        .a4-gst-invoice-root .accent-light-bg {
            background-color: color-mix(in srgb, var(--accent) 12%, #ffffff);
            color: var(--accent);
        }
        .a4-gst-invoice-root .accent-border {
            border-color: var(--accent);
        }
        .a4-gst-invoice-root .accent-text {
            color: var(--accent);
        }
        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .a4-gst-invoice-root {
                max-width: 100% !important;
                width: 100% !important;
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .screen-toolbar, .no-print {
                display: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 8mm 8mm 8mm 8mm;
            }
        }
    </style>

    {{-- ── 1. HEADER SECTION (Based on $headerLayout) ────────────────────────── --}}
    @if($headerLayout === 'centered')
        {{-- FORMAT 2: All Centered Brand (Image 2 Style) --}}
        <div style="position: relative; text-align: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #0f172a;">
            @if($receiptSettings->show_support_qr)
                <div style="position: absolute; right: 0; top: 0; text-align: center;" class="no-print-hide">
                    <img src="{{ $receiptSettings->getSupportQrUrl() }}" alt="Support QR" style="width: 55px; height: 55px; border: 1px solid #cbd5e1; border-radius: 3px;">
                    <div style="font-size: 8px; font-weight: bold; color: #64748b; margin-top: 2px;">Scan for Support</div>
                </div>
            @endif

            @if($showLogo)
                <img src="{{ $logoUrl }}" alt="Logo" style="max-width: {{ $logoWidth }}px; max-height: 60px; margin-bottom: 4px;">
            @endif
            <div style="font-size: 20px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; color: var(--accent);">
                {{ $storeName }}
            </div>
            @if($tagline)
                <div style="font-size: 10px; font-weight: 600; color: #475569; letter-spacing: 0.5px;">{{ $tagline }}</div>
            @endif
            <div style="font-size: 10.5px; color: #334155; margin-top: 3px;">
                {!! nl2br(e($address)) !!}
            </div>
            <div style="font-size: 10.5px; color: #334155; margin-top: 2px;">
                <strong>Phone:</strong> {{ $phone }} | <strong>Email:</strong> {{ $email }} | <strong>GSTIN:</strong> {{ $gstin }}
            </div>

            <div style="display: inline-block; background: #0f172a; color: #fff; font-weight: 800; font-size: 12px; letter-spacing: 1.5px; padding: 4px 24px; border-radius: 4px; margin-top: 8px;">
                TAX INVOICE
            </div>
        </div>

        {{-- Meta Grid below centered header --}}
        <div style="display: flex; justify-content: space-between; font-size: 10.5px; margin-bottom: 10px; background: #f8fafc; padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 4px;">
            <div style="width: 48%;">
                <div><strong>Invoice No. :</strong> {{ $salesBill->bill_number }}</div>
                <div><strong>Invoice Date :</strong> {{ $salesBill->bill_date ? $salesBill->bill_date->format('d/m/Y') : now()->format('d/m/Y') }}</div>
                <div><strong>Customer Type :</strong> {{ $customerGstin !== 'Unregistered' ? 'B2B (Registered)' : 'Retail (B2C)' }}</div>
            </div>
            <div style="width: 48%; text-align: right;">
                <div><strong>Place of Supply :</strong> {{ $salesBill->branch?->state ?: 'Gujarat (24)' }}</div>
                <div><strong>GST Status :</strong> {{ $customerGstin !== 'Unregistered' ? 'Registered' : 'Unregistered' }}</div>
                <div><strong>Reverse Charge :</strong> No</div>
            </div>
        </div>

    @elseif($headerLayout === 'logo_left_address_right')
        {{-- FORMAT 3: Split Sides (Image 3 Style) --}}
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
            <div style="width: 45%;">
                @if($showLogo)
                    <img src="{{ $logoUrl }}" alt="Logo" style="max-width: {{ $logoWidth }}px; max-height: 65px; margin-bottom: 4px;">
                @endif
                <div style="font-size: 20px; font-weight: 900; color: var(--accent); text-transform: uppercase;">
                    {{ $storeName }}
                </div>
                @if($tagline)
                    <div style="font-size: 9.5px; color: #64748b; font-weight: bold;">{{ $tagline }}</div>
                @endif
            </div>
            <div style="width: 52%; text-align: right; font-size: 10px; color: #334155; line-height: 1.4;">
                <div style="font-size: 9px; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">{{ $headerOfficeTitle }}</div>
                <div style="font-weight: bold; font-size: 12px; color: #0f172a;">{{ $storeName }}</div>
                <div>{!! nl2br(e($address)) !!}</div>
                <div><strong>Phone:</strong> {{ $phone }} | <strong>Email:</strong> {{ $email }}</div>
                <div><strong>GSTIN:</strong> {{ $gstin }}</div>
            </div>
        </div>

        {{-- Full-width Colored Banner --}}
        <div style="background-color: var(--accent); color: #fff; display: flex; justify-content: space-between; align-items: center; padding: 5px 12px; border-radius: 3px; font-weight: bold; margin-bottom: 10px;">
            <span style="font-size: 13px; letter-spacing: 1px;">TAX INVOICE</span>
            <span style="font-size: 10px; opacity: 0.9;">Original for Recipient</span>
        </div>

    @elseif($headerLayout === 'logo_right_address_left')
        {{-- Inverted Split: Address Left, Logo Right --}}
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
            <div style="width: 52%; font-size: 10px; color: #334155; line-height: 1.4;">
                <div style="font-weight: 900; font-size: 18px; color: var(--accent); text-transform: uppercase;">{{ $storeName }}</div>
                @if($tagline)<div style="font-size: 9.5px; color: #64748b; font-weight: bold; margin-bottom: 2px;">{{ $tagline }}</div>@endif
                <div>{!! nl2br(e($address)) !!}</div>
                <div><strong>Phone:</strong> {{ $phone }} | <strong>Email:</strong> {{ $email }}</div>
                <div><strong>GSTIN:</strong> {{ $gstin }}</div>
            </div>
            <div style="width: 45%; text-align: right;">
                @if($showLogo)
                    <img src="{{ $logoUrl }}" alt="Logo" style="max-width: {{ $logoWidth }}px; max-height: 65px; margin-bottom: 4px;">
                @endif
            </div>
        </div>

        <div style="background-color: var(--accent); color: #fff; display: flex; justify-content: space-between; align-items: center; padding: 5px 12px; border-radius: 3px; font-weight: bold; margin-bottom: 10px;">
            <span style="font-size: 13px; letter-spacing: 1px;">TAX INVOICE</span>
            <span style="font-size: 10px; opacity: 0.9;">Original for Recipient</span>
        </div>

    @else
        {{-- FORMAT 1 (Default): Logo Left + Address below Logo, Invoice Details on Right (Image 1 Style) --}}
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
            {{-- Left Column: Logo & Company Particulars --}}
            <div style="width: 56%;">
                @if($showLogo)
                    <img src="{{ $logoUrl }}" alt="Logo" style="max-width: {{ $logoWidth }}px; max-height: 60px; margin-bottom: 6px;">
                @endif
                <div style="font-size: 18px; font-weight: 800; color: var(--accent); text-transform: uppercase;">
                    {{ $storeName }}
                </div>
                @if($tagline)
                    <div style="font-size: 9.5px; font-weight: bold; color: #64748b; margin-bottom: 3px;">{{ $tagline }}</div>
                @endif
                <div style="font-size: 10px; color: #334155; line-height: 1.35; margin-top: 3px;">
                    <div>{!! nl2br(e($address)) !!}</div>
                    <div><strong>GSTIN:</strong> {{ $gstin }}</div>
                    <div><strong>Phone:</strong> {{ $phone }} | <strong>Email:</strong> {{ $email }}</div>
                </div>
            </div>

            {{-- Right Column: TAX INVOICE Box & Metadata Grid --}}
            <div style="width: 42%;">
                <div style="background: var(--accent); color: #fff; text-align: center; padding: 4px; border-radius: 3px 3px 0 0;">
                    <div style="font-weight: 800; font-size: 13px; letter-spacing: 1px;">TAX INVOICE</div>
                    <div style="font-size: 8.5px; opacity: 0.9; text-transform: uppercase;">Original for Recipient</div>
                </div>
                <div style="border: 1px solid var(--accent); border-top: none; padding: 4px 8px; font-size: 10px; background: #fff; border-radius: 0 0 3px 3px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="color: #475569; width: 95px; padding: 2px 0;">Invoice No.</td><td style="padding: 2px 0;">: <strong>{{ $salesBill->bill_number }}</strong></td></tr>
                        <tr><td style="color: #475569; padding: 2px 0;">Invoice Date</td><td style="padding: 2px 0;">: {{ $salesBill->bill_date ? $salesBill->bill_date->format('d/m/Y') : now()->format('d/m/Y') }}</td></tr>
                        <tr><td style="color: #475569; padding: 2px 0;">Order Ref</td><td style="padding: 2px 0;">: {{ $salesBill->sales_delivery_note_id ? 'DN-' . $salesBill->sales_delivery_note_id : ($salesBill->posting_key ?: 'ORD-' . $salesBill->id) }}</td></tr>
                        <tr><td style="color: #475569; padding: 2px 0;">Mode of Supply</td><td style="padding: 2px 0;">: {{ $customerGstin !== 'Unregistered' ? 'B2B Supply' : 'Retail Sale' }}</td></tr>
                        <tr><td style="color: #475569; padding: 2px 0;">Reverse Charge</td><td style="padding: 2px 0;">: No</td></tr>
                        <tr><td style="color: #475569; padding: 2px 0;">Place of Supply</td><td style="padding: 2px 0;">: {{ $salesBill->branch?->state ?: 'Gujarat (24)' }}</td></tr>
                        <tr><td style="color: #475569; padding: 2px 0;">State Code</td><td style="padding: 2px 0;">: 24</td></tr>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ── 2. BILL TO, SHIP TO & INVOICE DETAILS SECTION ────────────────────────── --}}
    <div style="display: flex; justify-content: space-between; margin-bottom: 12px; gap: 10px; align-items: stretch;">
        {{-- Bill To Card --}}
        <div style="flex: 1; border: 1px solid #cbd5e1; border-radius: 3px; overflow: hidden; background: #fff;">
            <div style="background: color-mix(in srgb, var(--accent) 10%, #f8fafc); border-bottom: 1px solid #cbd5e1; padding: 4px 8px; font-weight: bold; font-size: 10px; color: var(--accent); text-transform: uppercase;">
                BILL TO:
            </div>
            <div style="padding: 6px 8px; font-size: 10px; line-height: 1.45;">
                <table style="width: 100%;">
                    <tr><td style="width: 65px; color: #64748b;">Name</td><td>: <strong>{{ $customerName }}</strong></td></tr>
                    @if($customerMobile)
                        <tr><td style="color: #64748b;">Mobile</td><td>: {{ $customerMobile }}</td></tr>
                    @endif
                    @if($receiptSettings->show_customer_pet_name && $petName)
                        <tr><td style="color: #64748b;">Pet Name</td><td>: <strong>{{ $petName }}</strong></td></tr>
                    @endif
                    <tr><td style="color: #64748b; vertical-align: top;">Address</td><td>: {{ $customerAddress }}</td></tr>
                    @if($hasValidGstin)
                        <tr><td style="color: #64748b;">GSTIN</td><td>: <strong>{{ $customerGstin }}</strong></td></tr>
                    @endif
                </table>
            </div>
        </div>

        @if($receiptSettings->show_ship_to)
            {{-- Details of Consignee | Ship To: --}}
            <div style="flex: 1; border: 1px solid #cbd5e1; border-radius: 3px; overflow: hidden; background: #fff;">
                <div style="background: color-mix(in srgb, var(--accent) 10%, #f8fafc); border-bottom: 1px solid #cbd5e1; padding: 4px 8px; font-weight: bold; font-size: 10px; color: var(--accent); text-transform: uppercase;">
                    Details of Consignee | Ship To:
                </div>
                <div style="padding: 6px 8px; font-size: 10px; line-height: 1.45;">
                    <table style="width: 100%;">
                        <tr><td style="width: 60px; color: #64748b;">Details</td><td>: <em>Same as Billing Address</em></td></tr>
                        <tr><td style="color: #64748b;">Delivery</td><td>: Store Pickup / Local Delivery</td></tr>
                        <tr><td style="color: #64748b;">Contact</td><td>: {{ $customerMobile }}</td></tr>
                        <tr><td style="color: #64748b;">Place</td><td>: {{ $salesBill->branch?->state ?: 'Gujarat' }}</td></tr>
                    </table>
                </div>
            </div>
        @endif

        @if($headerLayout !== 'logo_left_address_below')
            {{-- In Split / Centered layouts: Invoice Details card beside parties --}}
            <div style="flex: 1; border: 1px solid #cbd5e1; border-radius: 3px; overflow: hidden; background: #fff;">
                <div style="background: color-mix(in srgb, var(--accent) 10%, #f8fafc); border-bottom: 1px solid #cbd5e1; padding: 4px 8px; font-weight: bold; font-size: 10px; color: var(--accent); text-transform: uppercase;">
                    Invoice Details:
                </div>
                <div style="padding: 6px 8px; font-size: 10px; line-height: 1.45;">
                    <table style="width: 100%;">
                        <tr><td style="width: 105px; color: #64748b;">Invoice No:</td><td><strong>{{ $salesBill->bill_number }}</strong></td></tr>
                        <tr><td style="color: #64748b;">Date:</td><td>{{ $salesBill->bill_date ? $salesBill->bill_date->format('d/m/Y') : now()->format('d/m/Y') }}</td></tr>
                        <tr><td style="color: #64748b;">Place of Supply:</td><td>{{ $salesBill->branch?->state ? ($salesBill->branch->state_code ? $salesBill->branch->state_code . ' - ' . $salesBill->branch->state : (str_contains($salesBill->branch->state, '-') ? $salesBill->branch->state : '24 - ' . $salesBill->branch->state)) : '24 - Gujarat' }}</td></tr>
                        <tr><td style="color: #64748b;">GST Type:</td><td><strong>{{ $customerGstType }}</strong></td></tr>
                        @if($salesBill->posting_key || $salesBill->sales_delivery_note_id)
                            <tr><td style="color: #64748b;">Order Ref:</td><td>{{ $salesBill->sales_delivery_note_id ? 'DN-' . $salesBill->sales_delivery_note_id : $salesBill->posting_key }}</td></tr>
                        @endif
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- ── 3. ITEMS TABLE ──────────────────────────────────────────────────────── --}}
    <div style="border: 1px solid #cbd5e1; border-radius: 3px; overflow: hidden; margin-bottom: 12px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 9.5px;">
            <thead>
                <tr style="background: var(--accent); color: #ffffff; text-align: left;">
                    <th style="width: 25px; text-align: center; border-right: 1px solid rgba(255,255,255,0.2);">Sr.</th>
                    <th style="width: 55px; text-align: center; border-right: 1px solid rgba(255,255,255,0.2);">HSN</th>
                    <th style="width: 65px; text-align: center; border-right: 1px solid rgba(255,255,255,0.2);">Item Code</th>
                    <th style="border-right: 1px solid rgba(255,255,255,0.2); padding-left: 6px;">Item Name</th>
                    <th style="width: 38px; text-align: right; border-right: 1px solid rgba(255,255,255,0.2);">Qty</th>
                    <th style="width: 55px; text-align: right; border-right: 1px solid rgba(255,255,255,0.2);">MRP (₹)</th>
                    <th style="width: 45px; text-align: right; border-right: 1px solid rgba(255,255,255,0.2);">Disc %</th>
                    <th style="width: 50px; text-align: right; border-right: 1px solid rgba(255,255,255,0.2);">SGST (₹)</th>
                    <th style="width: 50px; text-align: right; border-right: 1px solid rgba(255,255,255,0.2);">CGST (₹)</th>
                    <th style="width: 50px; text-align: right; border-right: 1px solid rgba(255,255,255,0.2);">IGST (₹)</th>
                    <th style="width: 68px; text-align: right;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($salesBill->items as $idx => $line)
                    @php
                        $itemObj = $line->item;
                        $itemCode = $itemObj?->item_code ?: ($line->item_code ?? '-');
                        $hsnCode = $itemObj?->hsn_code ?: ($line->hsn_code ?? '-');
                        $itemName = $itemObj?->name ?: ($line->item_name ?? 'Item');
                        $mrp = (float) ($line->mrp ?: $line->sell_price);
                        $qty = (float) ($line->qty ?? 1);
                        $discAmt = (float) ($line->disc_amount ?? 0);
                        $discPct = (float) ($line->disc_percent ?? 0);
                        if ($discPct <= 0 && $mrp > 0 && $discAmt > 0) {
                            $discPct = round(($discAmt / ($mrp * $qty)) * 100, 1);
                        }
                        $sgst = (float) ($line->sgst_amount ?? 0);
                        $cgst = (float) ($line->cgst_amount ?? 0);
                        $igst = (float) ($line->igst_amount ?? 0);
                        if ($sgst == 0 && $cgst == 0 && $igst == 0 && (float)($line->gst_tax_amount ?? 0) > 0) {
                            if ($isInterstate) {
                                $igst = (float) $line->gst_tax_amount;
                            } else {
                                $sgst = (float) ($line->gst_tax_amount / 2);
                                $cgst = (float) ($line->gst_tax_amount / 2);
                            }
                        }
                        $amount = (float) ($line->net_amount ?? 0);
                    @endphp
                    <tr style="border-bottom: 1px solid #e2e8f0; {{ $idx % 2 == 1 ? 'background: #f8fafc;' : '' }}">
                        <td style="text-align: center; border-right: 1px solid #e2e8f0; color: #64748b;">{{ $idx + 1 }}</td>
                        <td style="text-align: center; border-right: 1px solid #e2e8f0; color: #475569;">{{ $hsnCode }}</td>
                        <td style="text-align: center; border-right: 1px solid #e2e8f0; font-family: monospace; font-size: 8.5px; color: #334155;">{{ $itemCode }}</td>
                        <td style="border-right: 1px solid #e2e8f0; padding-left: 6px;">
                            <strong style="color: #0f172a;">{{ $itemName }}</strong>
                            @if($line->batch_no)
                                <span style="font-size: 8px; color: #64748b; margin-left: 4px;">[Batch: {{ $line->batch_no }}@if($line->exp_date) Exp: {{ $line->exp_date->format('m/y') }}@endif]</span>
                            @endif
                        </td>
                        <td style="text-align: right; border-right: 1px solid #e2e8f0; font-weight: 600;">{{ number_format($qty, 2) }}</td>
                        <td style="text-align: right; border-right: 1px solid #e2e8f0;">{{ number_format($mrp, 2) }}</td>
                        <td style="text-align: right; border-right: 1px solid #e2e8f0; color: {{ $discPct > 0 ? '#b91c1c' : '#64748b' }};">
                            {{ $discPct > 0 ? number_format($discPct, 1) . '%' : '-' }}
                        </td>
                        <td style="text-align: right; border-right: 1px solid #e2e8f0;">{{ $sgst > 0 ? number_format($sgst, 2) : '-' }}</td>
                        <td style="text-align: right; border-right: 1px solid #e2e8f0;">{{ $cgst > 0 ? number_format($cgst, 2) : '-' }}</td>
                        <td style="text-align: right; border-right: 1px solid #e2e8f0;">{{ $igst > 0 ? number_format($igst, 2) : '-' }}</td>
                        <td style="text-align: right; font-weight: 700; color: #0f172a;">{{ number_format($amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 20px; color: #94a3b8;">
                            No items recorded in this bill.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background: #f1f5f9; font-weight: 700; border-top: 1px solid #cbd5e1;">
                    <td colspan="4" style="text-align: right; padding-right: 8px;">Total:</td>
                    <td style="text-align: right;">{{ number_format($totalQty, 2) }}</td>
                    <td colspan="2"></td>
                    <td style="text-align: right;">{{ number_format((float) ($salesBill->total_sgst ?? ($isInterstate ? 0 : ($salesBill->total_gst / 2))), 2) }}</td>
                    <td style="text-align: right;">{{ number_format((float) ($salesBill->total_cgst ?? ($isInterstate ? 0 : ($salesBill->total_gst / 2))), 2) }}</td>
                    <td style="text-align: right;">{{ number_format((float) ($salesBill->total_igst ?? ($isInterstate ? $salesBill->total_gst : 0)), 2) }}</td>
                    <td style="text-align: right; font-weight: 800; color: var(--accent);">{{ number_format($salesBill->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- ── 4. SUMMARY SECTION (Tax Summary Left | Totals Right) ───────────────── --}}
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; gap: 12px;">
        {{-- Left Column: Tax Rate Summary Box --}}
        <div style="width: 54%;">
            @if($receiptSettings->show_tax_summary_table)
                <div style="border: 1px solid #cbd5e1; border-radius: 3px; overflow: hidden; margin-bottom: 8px;">
                    <div style="background: color-mix(in srgb, var(--accent) 12%, #f8fafc); border-bottom: 1px solid #cbd5e1; padding: 3px 8px; font-weight: bold; font-size: 9.5px; color: var(--accent);">
                        Tax Summary (By GST Rate)
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 9px;">
                        <thead>
                            <tr style="background: #f1f5f9; border-bottom: 1px solid #cbd5e1; color: #475569;">
                                <th style="text-align: center;">GST %</th>
                                <th style="text-align: right;">Taxable (₹)</th>
                                @if($isInterstate)
                                    <th style="text-align: right;">IGST (₹)</th>
                                @else
                                    <th style="text-align: right;">CGST (₹)</th>
                                    <th style="text-align: right;">SGST (₹)</th>
                                @endif
                                <th style="text-align: right;">Total Tax (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($taxSummary as $tx)
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="text-align: center; font-weight: bold;">{{ $tx['rate_label'] }}</td>
                                    <td style="text-align: right;">{{ number_format($tx['taxable'], 2) }}</td>
                                    @if($isInterstate)
                                        <td style="text-align: right;">{{ number_format($tx['igst'], 2) }}</td>
                                    @else
                                        <td style="text-align: right;">{{ number_format($tx['cgst'], 2) }}</td>
                                        <td style="text-align: right;">{{ number_format($tx['sgst'], 2) }}</td>
                                    @endif
                                    <td style="text-align: right; font-weight: 600;">{{ number_format($tx['total_tax'], 2) }}</td>
                                </tr>
                            @endforeach
                            <tr style="background: #f8fafc; font-weight: bold;">
                                <td style="text-align: center;">Total</td>
                                <td style="text-align: right;">{{ number_format($taxableTotal, 2) }}</td>
                                @if($isInterstate)
                                    <td style="text-align: right;">{{ number_format((float) ($salesBill->total_igst ?? 0), 2) }}</td>
                                @else
                                    <td style="text-align: right;">{{ number_format(((float) ($salesBill->total_gst ?? 0)) / 2, 2) }}</td>
                                    <td style="text-align: right;">{{ number_format(((float) ($salesBill->total_gst ?? 0)) / 2, 2) }}</td>
                                @endif
                                <td style="text-align: right;">{{ number_format((float) ($salesBill->total_gst ?? 0), 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif

            <div style="font-size: 10px; color: #475569; background: #f8fafc; padding: 4px 8px; border: 1px solid #e2e8f0; border-radius: 3px;">
                <strong>Total Quantity:</strong> {{ number_format($totalQty) }} &nbsp;|&nbsp; <strong>Total Items:</strong> {{ $totalItems }}
            </div>
        </div>

        {{-- Right Column: Invoice Totals Card --}}
        <div style="width: 44%;">
            <div style="border: 1px solid #cbd5e1; border-radius: 3px; overflow: hidden; background: #fff;">
                <table style="width: 100%; border-collapse: collapse; font-size: 10px; line-height: 1.45;">
                    <tr>
                        <td style="color: #64748b; padding: 3px 8px;">Total Value (Before Discount)</td>
                        <td style="text-align: right; padding: 3px 8px;">₹ {{ number_format($totalBeforeDisc, 2) }}</td>
                    </tr>
                    @if($totalDiscount > 0)
                        <tr style="color: #b91c1c;">
                            <td style="padding: 3px 8px;">Total Discount</td>
                            <td style="text-align: right; padding: 3px 8px;">- ₹ {{ number_format($totalDiscount, 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="color: #64748b; padding: 3px 8px;">Taxable Amount</td>
                        <td style="text-align: right; padding: 3px 8px; font-weight: 600;">₹ {{ number_format($taxableTotal, 2) }}</td>
                    </tr>
                    @if($isInterstate)
                        <tr>
                            <td style="color: #64748b; padding: 3px 8px;">IGST Total</td>
                            <td style="text-align: right; padding: 3px 8px;">₹ {{ number_format((float) ($salesBill->total_igst ?? 0), 2) }}</td>
                        </tr>
                    @else
                        <tr>
                            <td style="color: #64748b; padding: 3px 8px;">CGST Total</td>
                            <td style="text-align: right; padding: 3px 8px;">₹ {{ number_format(((float) ($salesBill->total_gst ?? 0)) / 2, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding: 3px 8px;">SGST Total</td>
                            <td style="text-align: right; padding: 3px 8px;">₹ {{ number_format(((float) ($salesBill->total_gst ?? 0)) / 2, 2) }}</td>
                        </tr>
                    @endif
                    @if(!empty($salesBill->round_off) && (float) $salesBill->round_off != 0)
                        <tr>
                            <td style="color: #64748b; padding: 3px 8px;">Round Off</td>
                            <td style="text-align: right; padding: 3px 8px;">{{ number_format((float) $salesBill->round_off, 2) }}</td>
                        </tr>
                    @endif
                    <tr style="background: var(--accent); color: #ffffff; font-weight: 800; font-size: 13px;">
                        <td style="padding: 6px 8px; letter-spacing: 0.5px;">Grand Total</td>
                        <td style="text-align: right; padding: 6px 8px;">₹ {{ number_format($salesBill->total, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    {{-- ── 5. AMOUNT IN WORDS BANNER ─────────────────────────────────────────── --}}
    <div style="background: color-mix(in srgb, var(--accent) 8%, #f8fafc); border: 1px solid color-mix(in srgb, var(--accent) 25%, #e2e8f0); border-radius: 3px; padding: 5px 10px; font-size: 10px; margin-bottom: 12px;">
        <strong>Amount in Words :</strong> <span style="color: #0f172a; font-weight: 600;">{{ $amountInWords }}</span>
    </div>

    {{-- ── 6. BOTTOM CARDS (Payment Details | E-Invoice QR / Compliance | Signatory) ─ --}}
    <div style="display: flex; justify-content: space-between; align-items: stretch; margin-bottom: 12px; gap: 10px;">
        {{-- Card A: Payment Details & QR --}}
        @if($receiptSettings->show_payment_details)
            <div style="flex: 1.1; border: 1px solid #cbd5e1; border-radius: 3px; padding: 6px 8px; font-size: 9.5px; background: #fff;">
                <div style="font-weight: bold; color: var(--accent); border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">
                    PAYMENT {!! $receiptSettings->show_upi_qr ? '&amp; UPI ' : '' !!}DETAILS
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="line-height: 1.5;">
                        <div><strong style="color: #64748b;">Mode:</strong> {{ $paymentModeStr ?: 'Cash / UPI' }}</div>
                        <div><strong style="color: #64748b;">Ref:</strong> {{ $paymentRefStr ?: ($salesBill->bill_number ?: 'IN01/2627/001002') }}</div>
                        @if($receiptSettings->show_upi_qr && $receiptSettings->upi_id)
                            <div><strong style="color: #64748b;">UPI:</strong> {{ $receiptSettings->upi_id }}</div>
                        @endif
                        <div style="color: #16a34a; font-weight: bold; margin-top: 2px;">
                            <i class="fas fa-check-circle"></i> Payment Settled (₹ {{ number_format($salesBill->total, 2) }})
                        </div>
                    </div>
                    @if($receiptSettings->show_upi_qr)
                        <div style="text-align: center; margin-left: 6px;">
                            <img src="{{ $receiptSettings->getUpiQrUrl($salesBill->total, $salesBill->bill_number) }}" alt="Scan to Pay" style="width: 55px; height: 55px; border: 1px solid #e2e8f0; border-radius: 2px;">
                            <div style="font-size: 7.5px; color: #64748b; font-weight: bold;">Scan to Pay</div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Card B: E-Invoice IRN QR or Compliance Notes --}}
        <div style="flex: 1.1; border: 1px solid #cbd5e1; border-radius: 3px; padding: 6px 8px; font-size: 9px; background: #fff;">
            @if($salesBill->hasIrn() || $receiptSettings->compliance_notes)
                <div style="font-weight: bold; color: var(--accent); border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 4px;">
                    Notes / Statutory Compliance
                </div>
                @if($salesBill->hasIrn())
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="font-size: 8px; line-height: 1.3; color: #475569;">
                            <div><strong>IRN:</strong> {{ substr($salesBill->irn, 0, 20) }}...</div>
                            <div><strong>Ack No:</strong> {{ $salesBill->ack_no ?: 'N/A' }}</div>
                        </div>
                        <img src="{{ $salesBill->getEinvoiceQrUrl() }}" alt="IRN QR" style="width: 50px; height: 50px;">
                    </div>
                @else
                    <div style="color: #475569; line-height: 1.35;">
                        {!! nl2br(e($receiptSettings->compliance_notes ?: "1. HSN/SAC shown item-wise.\n2. Taxable value shown after discount.\n3. Goods once sold cannot be returned without bill.")) !!}
                    </div>
                @endif
            @else
                <div style="font-weight: bold; color: var(--accent); border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 4px;">
                    Store Guarantee
                </div>
                <div style="color: #475569; line-height: 1.35;">
                    1. 100% Genuine and authenticated products.<br>
                    2. Check batch no and expiry before accepting.<br>
                    3. Computer generated invoice, valid across all branches.
                </div>
            @endif
        </div>

        {{-- Card C: Authorised Signatory Box --}}
        @if($receiptSettings->show_signature_box)
            <div style="flex: 0.9; border: 1px solid #cbd5e1; border-radius: 3px; padding: 6px 8px; text-align: center; display: flex; flex-direction: column; justify-content: space-between; background: #fff;">
                <div style="font-size: 9.5px; font-weight: bold; color: #0f172a;">
                    For {{ $storeName }}
                </div>
                <div style="margin-top: 35px; border-top: 1px solid #0f172a; padding-top: 2px; font-size: 9px; font-weight: bold; color: #475569;">
                    Authorised Signatory
                </div>
            </div>
        @endif
    </div>

    {{-- Barcode at bottom (if enabled) --}}
    @if($receiptSettings->show_barcode)
        <div style="text-align: center; margin-top: 6px; margin-bottom: 4px;">
            <div style="display: inline-block; height: 26px; width: 160px; background: repeating-linear-gradient(90deg, #000 0px, #000 2px, #fff 2px, #fff 4px, #000 4px, #000 5px, #fff 5px, #fff 8px);"></div>
            <div style="font-size: 8.5px; font-weight: bold; letter-spacing: 1.5px; color: #334155; margin-top: 1px;">
                * {{ $salesBill->bill_number }} *
            </div>
        </div>
    @endif

    {{-- ── 7. TERMS & CONDITIONS FOOTER ───────────────────────────────────────── --}}
    <div style="border-top: 1px solid #cbd5e1; padding-top: 8px; font-size: 8.5px; color: #64748b; line-height: 1.35;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
            <div style="width: 70%;">
                <div style="font-weight: bold; color: #334155; margin-bottom: 2px;">Terms &amp; Conditions / Return Policy:</div>
                <div>{!! nl2br(e($receiptSettings->terms_conditions ?: ($receiptSettings->footer_policy ?: "1. Please check goods at the time of purchase/delivery for any damage or defect.\n2. Returns or exchanges accepted only with original bill within 7 days.\n3. Subject to local jurisdiction."))) !!}</div>
            </div>
            <div style="width: 28%; text-align: right; color: var(--accent); font-weight: bold; font-size: 9.5px;">
                <div>{{ $receiptSettings->footer_note ?: 'Thank You for Shopping! 🐾' }}</div>
                <div style="font-size: 8px; color: #94a3b8; font-weight: normal; margin-top: 2px;">{{ $email }}</div>
            </div>
        </div>
    </div>
</div>
