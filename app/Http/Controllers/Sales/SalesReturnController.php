<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesReturn;
use App\Services\Accounting\FinancialYearGuard;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\StockLedgerService;
use App\Services\Tax\TaxEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReturnController extends Controller
{
    public function __construct(
        private LedgerPostingService $ledgerPosting,
        private StockLedgerService $stockLedger,
        private TaxEngine $taxEngine,
        private AuditLogger $auditLogger,
        private FinancialYearGuard $financialYearGuard,
    ) {
    }

    public function index(Request $request)
    {
        $query = SalesReturn::with(['customer', 'branch', 'salesBill'])->latest('return_date');

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('return_number', 'like', "%{$term}%")
                    ->orWhereHas('salesBill', fn ($bq) => $bq->where('bill_number', 'like', "%{$term}%"))
                    ->orWhereHas('customer', function ($cq) use ($term) {
                        $cq->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%")
                            ->orWhere('customer_code', 'like', "%{$term}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('return_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('return_date', '<=', $request->input('date_to'));
        }

        $branchFilter = $request->has('branch_id')
            ? $request->input('branch_id')
            : session('active_branch_id', auth()->user()?->branch_id);

        if (!empty($branchFilter) && $branchFilter !== 'all') {
            $query->where('branch_id', $branchFilter);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('return_mode')) {
            $query->where('return_mode', $request->input('return_mode'));
        }

        $salesReturns = $query->paginate(20)->withQueryString();
        $branches = Branch::orderBy('name')->pluck('name', 'id');
        $customers = Customer::where('status', true)->orderBy('name')->limit(30)->get(['id', 'name', 'mobile'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->mobile ? "{$c->name} ({$c->mobile})" : $c->name]);
        $returnModes = ['Cash', 'Credit Note', 'Replacement'];

        return view('sales.sales-returns.index', compact('salesReturns', 'branches', 'customers', 'returnModes'));
    }

    public function create(Request $request)
    {
        $presetCustId = $request->filled('customer_id') ? (int) $request->input('customer_id') : null;
        $presetBillId = $request->filled('sales_bill_id') ? (int) $request->input('sales_bill_id') : null;

        $options = $this->formOptions(null, $presetCustId, $presetBillId);
        $options['presetCustomerId'] = $presetCustId;
        $options['presetBillId'] = $presetBillId;

        return view('sales.sales-returns.create', $options);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['return_date']);

        if ($data['header']['posting_key'] ?? null) {
            $existing = SalesReturn::where('posting_key', $data['header']['posting_key'])->first();
            if ($existing) {
                return redirect()->route('sales.sales-returns.index')->with('status', "Sales Return {$existing->return_number} created successfully.");
            }
        }

        $salesReturn = DB::transaction(function () use ($data) {
            $lines = $this->computeLines($data['items'], $data['header']);
            $totals = $this->computeTotals($lines, $data);

            $salesReturn = SalesReturn::create(array_merge($data['header'], $totals, [
                'return_number' => $this->nextNumber(),
            ]));

            $createdItems = $salesReturn->items()->createMany($lines);
            $this->postStock($createdItems, $salesReturn);
            $this->ledgerPosting->postSalesReturn($salesReturn);

            return $salesReturn;
        });

        return redirect()->route('sales.sales-returns.index')->with('status', "Sales Return {$salesReturn->return_number} created successfully.");
    }

    public function show(SalesReturn $salesReturn)
    {
        $salesReturn->load(['customer', 'branch', 'salesBill', 'items.item']);

        return view('sales.sales-returns.show', compact('salesReturn'));
    }

    public function print(SalesReturn $salesReturn)
    {
        $salesReturn->load(['customer', 'branch', 'salesBill', 'items.item']);

        return view('sales.sales-returns.print', compact('salesReturn'));
    }

    public function edit(SalesReturn $salesReturn)
    {
        $salesReturn->load('items');

        return view('sales.sales-returns.edit', array_merge(['salesReturn' => $salesReturn], $this->formOptions()));
    }

    public function update(Request $request, SalesReturn $salesReturn)
    {
        $salesReturn->assertEditable();

        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['return_date']);

        DB::transaction(function () use ($data, $salesReturn) {
            $this->stockLedger->reverseByReference(SalesReturn::class, $salesReturn->id);

            $lines = $this->computeLines($data['items'], $data['header']);
            $totals = $this->computeTotals($lines, $data);

            $salesReturn->update(array_merge($data['header'], $totals));
            $salesReturn->items()->delete();
            $createdItems = $salesReturn->items()->createMany($lines);

            $this->postStock($createdItems, $salesReturn);
            $this->ledgerPosting->postSalesReturn($salesReturn);
        });

        return redirect()->route('sales.sales-returns.index')->with('status', "Sales Return {$salesReturn->return_number} updated successfully.");
    }

    public function destroy(SalesReturn $salesReturn)
    {
        // No assertEditable() guard here: destroy() already IS the cancel/reversal action.
        $oldValues = $salesReturn->only(['return_number', 'return_date', 'customer_id', 'branch_id', 'total', 'status']);

        DB::transaction(function () use ($salesReturn, $oldValues) {
            $this->stockLedger->reverseByReference(SalesReturn::class, $salesReturn->id);
            $this->ledgerPosting->reverse(SalesReturn::class, $salesReturn->id);
            $this->auditLogger->log('cancel', $salesReturn, $oldValues, null);
            $salesReturn->delete();
        });

        return redirect()->route('sales.sales-returns.index')->with('status', 'Sales Return deleted and stock reversed.');
    }

    /**
     * AJAX endpoint: return items from a sales bill for quick population.
     */
    public function billItems(SalesBill $salesBill, Request $request)
    {
        $ignoreReturnId = $request->integer('ignore_return_id');

        // Calculate already returned quantities for each item in this sales bill
        $alreadyReturnedByItem = DB::table('sales_return_items as sri')
            ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
            ->where('sr.sales_bill_id', $salesBill->id)
            ->when($ignoreReturnId, fn ($q) => $q->where('sr.id', '!=', $ignoreReturnId))
            ->groupBy('sri.item_id')
            ->select('sri.item_id', DB::raw('SUM(sri.qty) as returned_qty'))
            ->pluck('returned_qty', 'item_id')
            ->all();

        $items = $salesBill->items()->with('item')->get()->map(function ($line) use ($alreadyReturnedByItem) {
            $originalQty = (float) $line->qty;
            $alreadyReturned = (float) ($alreadyReturnedByItem[$line->item_id] ?? 0);
            $remainingQty = max(0, round($originalQty - $alreadyReturned, 4));

            $discPercent = (float) ($line->disc_percent ?? 0);
            $discAmount = 0.0;
            if ($remainingQty > 0) {
                if ($discPercent > 0) {
                    $discAmount = round((($remainingQty * (float)$line->sell_price) * $discPercent / 100), 2);
                } else if ($originalQty > 0) {
                    $discAmount = round(((float)($line->disc_amount ?? 0) / $originalQty) * $remainingQty, 2);
                }
            }

            return [
                'item_id'              => $line->item_id,
                'item_name'            => $line->item?->name ?? 'Unknown',
                'item_code'            => $line->item?->item_code ?? $line->item?->ean_upc_code ?? '',
                'exp_date'             => $line->exp_date ? $line->exp_date->format('Y-m-d') : null,
                'original_qty'         => $originalQty,
                'already_returned_qty' => $alreadyReturned,
                'remaining_qty'        => $remainingQty,
                'qty'                  => $remainingQty,
                'sell_price'           => (float) $line->sell_price,
                'mrp'                  => (float) ($line->mrp ?? 0),
                'disc_percent'         => $discPercent,
                'disc_amount'          => $discAmount,
                'gst_percent'          => (float) ($line->gst_percent ?? 0),
                'net_amount'           => (float) ($line->net_amount ?? 0),
            ];
        });

        return response()->json([
            'customer_id'  => $salesBill->customer_id,
            'branch_id'    => $salesBill->branch_id,
            'sales_type'   => $salesBill->sales_type,
            'bill_number'  => $salesBill->bill_number,
            'bill_date'    => $salesBill->bill_date ? $salesBill->bill_date->format('d-m-Y') : '',
            'bill_total'   => (float) ($salesBill->total ?? 0),
            'disc_amount'  => (float) ($salesBill->disc_amount ?? 0),
            'total_gst'    => (float) ($salesBill->total_gst ?? 0),
            'round_off'    => (float) ($salesBill->round_off ?? 0),
            'items'        => $items,
        ]);
    }

    /**
     * AJAX endpoint: return all non-cancelled sales bills for a specific customer.
     */
    public function customerBills(Customer $customer)
    {
        $bills = SalesBill::where('customer_id', $customer->id)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'Cancelled');
            })
            ->latest('bill_date')
            ->get(['id', 'bill_number', 'bill_date', 'total'])
            ->map(function ($b) {
                $dateStr = $b->bill_date ? $b->bill_date->format('d-m-Y') : '';
                return [
                    'id' => $b->id,
                    'bill_number' => $b->bill_number,
                    'bill_date' => $dateStr,
                    'total' => (float) $b->total,
                    'label' => "{$b->bill_number} (" . ($dateStr ? $dateStr . ' - ' : '') . "₹" . number_format($b->total, 2) . ")",
                ];
            });

        return response()->json($bills);
    }

    /**
     * AJAX endpoint: return total qty sold for an item (optionally per customer).
     * Used by SR form when no specific sales bill is selected — prevents return qty > total sold qty.
     */
    public function itemSoldQty(Request $request)
    {
        $itemId    = $request->integer('item_id');
        $customerId = $request->integer('customer_id');
        $ignoreReturnId = $request->integer('ignore_return_id'); // for edit mode

        if (!$itemId) {
            return response()->json(['total_sold' => null, 'already_returned' => 0, 'available' => null]);
        }

        $soldQuery = SalesBillItem::where('item_id', $itemId)
            ->whereHas('salesBill', function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('status')->orWhere('status', '!=', 'Cancelled');
                });
            });

        if ($customerId) {
            $soldQuery->whereHas('salesBill', function ($q) use ($customerId) {
                $q->where('customer_id', $customerId);
            });
        }

        $totalSold = (float) $soldQuery->sum('qty');

        // Calculate already returned qty (from other returns)
        $returnedQuery = \App\Models\SalesReturnItem::where('item_id', $itemId);
        if ($ignoreReturnId) {
            $returnedQuery->where('sales_return_id', '!=', $ignoreReturnId);
        }
        if ($customerId) {
            $returnedQuery->whereHas('salesReturn', function ($q) use ($customerId) {
                $q->where('customer_id', $customerId);
            });
        }
        $alreadyReturned = (float) $returnedQuery->sum('qty');

        $available = max(0, $totalSold - $alreadyReturned);

        return response()->json([
            'total_sold'       => $totalSold,
            'already_returned' => $alreadyReturned,
            'available'        => $available,
        ]);
    }


    /**
     * Restores stock at the ORIGINAL sale's cost_at_sale when the return is linked to a
     * sales bill (per foundation spec: a return restores the original cost basis, not
     * today's average) — falls back to cost-neutral (current average) when unlinked.
     */
    private function postStock($createdItems, SalesReturn $salesReturn): void
    {
        foreach ($createdItems as $itemLine) {
            $originalCost = null;
            if ($salesReturn->sales_bill_id) {
                $originalCost = SalesBillItem::where('sales_bill_id', $salesReturn->sales_bill_id)
                    ->where('item_id', $itemLine->item_id)
                    ->value('cost_at_sale');
            }

            $ledgerRow = $this->stockLedger->post(
                itemId: $itemLine->item_id,
                branchId: $salesReturn->branch_id,
                movementType: 'SALE_RETURN',
                qtyDelta: (float) $itemLine->qty,
                unitCost: $originalCost !== null ? (float) $originalCost : null,
                referenceType: SalesReturn::class,
                referenceId: $salesReturn->id,
                documentDate: $salesReturn->return_date->toDateString(),
                expDate: $itemLine->exp_date?->toDateString(),
            );

            $itemLine->update(['cost_at_sale' => $ledgerRow->unit_cost]);
        }
    }

    private function nextNumber(): string
    {
        $branchId = session('active_branch_id', auth()->user()?->branch_id);

        return app(\App\Services\Accounting\DocumentNumberingService::class)->generate(
            'sales_return',
            $branchId ? (int) $branchId : null
        );
    }

    private function formOptions(?SalesReturn $salesReturn = null, ?int $presetCustomerId = null, ?int $presetBillId = null): array
    {
        $custId = old('customer_id', $salesReturn?->customer_id ?? $presetCustomerId);
        $customers = Customer::where('status', true)
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'mobile'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->mobile ? "{$c->name} ({$c->mobile})" : $c->name]);

        if ($custId && ! isset($customers[$custId])) {
            $selCust = Customer::find($custId);
            if ($selCust) {
                $customers->put($selCust->id, $selCust->mobile ? "{$selCust->name} ({$selCust->mobile})" : $selCust->name);
            }
        }

        $salesBills = $custId
            ? SalesBill::where('customer_id', $custId)
                ->where(fn($q) => $q->whereNull('status')->orWhere('status', '!=', 'Cancelled'))
                ->latest('bill_date')
                ->pluck('bill_number', 'id')
                ->all()
            : [];

        if ($presetBillId && !isset($salesBills[$presetBillId])) {
            $pb = SalesBill::find($presetBillId);
            if ($pb) {
                $salesBills[$pb->id] = $pb->bill_number;
            }
        }

        return [
            'customers' => $customers,
            'branches'  => Branch::orderBy('name')->pluck('name', 'id'),
            'items'     => collect(), // Items resolved per-bill via AJAX — never load full catalogue
            'salesBills' => $salesBills,
        ];
    }

    private function computeLines(array $items, array $header): array
    {
        $itemsById = Item::with('gstTax')->whereIn('id', collect($items)->pluck('item_id')->unique())->get()->keyBy('id');
        $isInterstate = ($header['sales_type'] ?? null) === 'Interstate';

        return collect($items)->map(function ($line) use ($itemsById, $isInterstate) {
            $qty = (float) $line['qty'];
            $sellPrice = (float) $line['sell_price'];
            $item = $itemsById[$line['item_id']];

            $discPercent = (float) ($line['disc_percent'] ?? 0);
            $discAmount = (float) ($line['disc_amount'] ?? 0);

            // GST included in price (Tax Inclusive) matching Sales Bill (Task 7)
            $tax = $this->taxEngine->calculate(
                $qty,
                $sellPrice,
                $item,
                $discPercent,
                $discAmount,
                0.0,
                $isInterstate,
                isTaxInclusive: true
            );

            return [
                'item_id' => $line['item_id'],
                'exp_date' => $this->normalizeDate($line['exp_date'] ?? null),
                'qty' => $qty,
                'sell_price' => $sellPrice,
                'mrp' => (float) ($line['mrp'] ?? 0),
                'disc_percent' => $discPercent,
                'disc_amount' => $tax['disc_amount'],
                'gst_percent' => $tax['gst_percent'],
                'gst_tax_amount' => $tax['gst_tax_amount'],
                'cgst_amount' => $tax['cgst_amount'],
                'sgst_amount' => $tax['sgst_amount'],
                'igst_amount' => $tax['igst_amount'],
                'net_amount' => $tax['net_amount'],
            ];
        })->all();
    }

    private function computeTotals(array $lines, array $data): array
    {
        $collection = collect($lines);
        $roundOff = (float) ($data['header']['round_off'] ?? 0);
        $totalExtraCess = (float) ($data['header']['total_extra_cess'] ?? 0);
        $gstCalamityCess = (float) ($data['header']['gst_calamity_cess'] ?? 0);

        return [
            'item_disc_amount' => $collection->sum('disc_amount'),
            'disc_amount' => $collection->sum('disc_amount'),
            'total_gst' => $collection->sum('gst_tax_amount'),
            'total_cgst' => $collection->sum('cgst_amount'),
            'total_sgst' => $collection->sum('sgst_amount'),
            'total_igst' => $collection->sum('igst_amount'),
            'total' => round($collection->sum('net_amount') + $roundOff + $totalExtraCess + $gstCalamityCess, 2),
        ];
    }

    private function validateData(Request $request): array
    {
        $rawItems = $request->input('items', []);
        $filteredItems = collect($rawItems)->filter(function ($item) {
            return !empty($item['item_id']) && (float)($item['qty'] ?? 0) > 0;
        })->values()->all();
        $request->merge(['items' => $filteredItems]);

        $headerRules = [
            'return_date' => ['required', 'date'],
            'customer_id' => ['required', 'exists:customers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'sales_bill_id' => ['nullable', 'exists:sales_bills,id'],
            'return_mode' => ['required', 'in:RRN,Credit Note,Cash,Wallet,Card'],
            'sales_type' => ['required', 'in:Local,Interstate'],
            'round_off' => ['nullable', 'numeric'],
            'total_extra_cess' => ['nullable', 'numeric', 'min:0'],
            'gst_calamity_cess' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'posting_key' => ['nullable', 'string', 'max:100'],
        ];

        $headerMessages = [];
        app(\App\Services\DynamicValidationService::class)->applyTo('sales_returns', $headerRules, $headerMessages);
        $header = $request->validate($headerRules, $headerMessages);

        $header['return_date'] = $this->normalizeDate($header['return_date']);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.exp_date' => ['nullable', 'date'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.sell_price' => ['required', 'numeric', 'min:0'],
            'items.*.mrp' => ['nullable', 'numeric', 'min:0'],
            'items.*.disc_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.disc_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (!empty($header['sales_bill_id'])) {
            $bill = SalesBill::with('items.item')->find($header['sales_bill_id']);
            if ($bill) {
                $billItemQtys = $bill->items->groupBy('item_id')->map->sum('qty');

                // Group requested quantities across submitted rows by item_id
                $totalQtyByItem = [];
                foreach ($validated['items'] as $itemLine) {
                    $itemId = (int) $itemLine['item_id'];
                    $totalQtyByItem[$itemId] = ($totalQtyByItem[$itemId] ?? 0.0) + (float) $itemLine['qty'];
                }

                $currentReturnId = $request->route('sales_return') 
                    ? (is_object($request->route('sales_return')) ? $request->route('sales_return')->id : (int)$request->route('sales_return')) 
                    : null;

                $alreadyReturnedQtys = DB::table('sales_return_items as sri')
                    ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
                    ->where('sr.sales_bill_id', $bill->id)
                    ->when($currentReturnId, fn($q) => $q->where('sr.id', '!=', $currentReturnId))
                    ->whereIn('sri.item_id', array_keys($totalQtyByItem))
                    ->groupBy('sri.item_id')
                    ->select('sri.item_id', DB::raw('SUM(sri.qty) as returned_qty'))
                    ->pluck('returned_qty', 'item_id')
                    ->all();

                foreach ($totalQtyByItem as $itemId => $requestedQty) {
                    if (!isset($billItemQtys[$itemId])) {
                        $itemModel = Item::find($itemId);
                        $name = $itemModel?->name ?? "Item #{$itemId}";
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items' => ["The item '{$name}' does not belong to Sales Bill #{$bill->bill_number}."]
                        ]);
                    }

                    $origQty = (float) $billItemQtys[$itemId];
                    $alreadyReturned = (float) ($alreadyReturnedQtys[$itemId] ?? 0);
                    $remaining = max(0, round($origQty - $alreadyReturned, 4));

                    if ($remaining <= 0) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items' => ["No returnable quantity available for this item."]
                        ]);
                    }

                    if (round($requestedQty, 4) > round($remaining, 4)) {
                        $remDisplay = ($remaining == (int)$remaining) ? (int)$remaining : $remaining;
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items' => ["Return quantity cannot exceed the remaining returnable quantity of {$remDisplay}."]
                        ]);
                    }
                }
            }
        }

        // Enforce Task 6: Only items purchased by this customer can be returned
        $customerId = (int) ($header['customer_id'] ?? 0);
        if ($customerId > 0) {
            $itemIds = collect($validated['items'])->pluck('item_id')->unique()->all();
            $purchasedItemIds = \App\Models\SalesBillItem::whereIn('item_id', $itemIds)
                ->whereHas('salesBill', function ($q) use ($customerId) {
                    $q->where('customer_id', $customerId)
                      ->where(fn($sq) => $sq->whereNull('status')->orWhere('status', '!=', 'Cancelled'));
                })
                ->pluck('item_id')
                ->unique()
                ->all();

            $invalidItems = array_diff($itemIds, $purchasedItemIds);
            if (!empty($invalidItems)) {
                $names = Item::whereIn('id', $invalidItems)->pluck('name')->implode(', ');
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => ["Cannot return item(s) [{$names}]: This customer has not purchased them."]
                ]);
            }
        }

        return ['header' => $header, 'items' => $validated['items']];
    }
}
