<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\TenderType;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Services\Accounting\CreditLimitGuard;
use App\Services\Accounting\FinancialYearGuard;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\StockLedgerService;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Tax\TaxEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesBillController extends Controller
{
    public function __construct(
        private LedgerPostingService $ledgerPosting,
        private StockLedgerService $stockLedger,
        private TaxEngine $taxEngine,
        private AuditLogger $auditLogger,
        private CreditLimitGuard $creditLimitGuard,
        private FinancialYearGuard $financialYearGuard,
        private LoyaltyService $loyaltyService,
    ) {
    }

    public function index(Request $request)
    {
        $query = SalesBill::with(['customer', 'branch'])->orderByDesc('id');

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('bill_number', 'like', "%{$term}%")
                    ->orWhereHas('customer', function ($cq) use ($term) {
                        $cq->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%")
                            ->orWhere('customer_code', 'like', "%{$term}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('bill_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('bill_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('invoice_type')) {
            $query->where('invoice_type', $request->input('invoice_type'));
        }

        $salesBills = $query->paginate(20)->withQueryString();
        $branches = Branch::orderBy('name')->pluck('name', 'id');
        $filteredCustId = $request->input('customer_id');
        $customers = Customer::where('status', true)
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'mobile'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->mobile ? "{$c->name} ({$c->mobile})" : $c->name]);

        if ($filteredCustId && ! isset($customers[$filteredCustId])) {
            $fc = Customer::find($filteredCustId);
            if ($fc) {
                $customers->put($fc->id, $fc->mobile ? "{$fc->name} ({$fc->mobile})" : $fc->name);
            }
        }

        $invoiceTypes = ['Retail Invoice', 'Tax Invoice', 'Exempted'];

        return view('sales.sales-bills.index', compact('salesBills', 'branches', 'customers', 'invoiceTypes'));
    }

    public function create(Request $request)
    {
        $options = [];
        $convertedItems = null;
        $sourceCustId = null;

        if ($request->filled('from_quotation')) {
            $quotation = SalesQuotation::with(['items.item.gstTax', 'customer', 'branch'])
                ->findOrFail($request->input('from_quotation'));
            $options['sourceQuotation'] = $quotation;
            $options['convertedItems'] = $quotation->items;
            $convertedItems = $quotation->items;
            $sourceCustId = $quotation->customer_id;
        } elseif ($request->filled('from_order')) {
            $order = SalesOrder::with(['items.item.gstTax', 'customer', 'branch'])
                ->findOrFail($request->input('from_order'));
            $options['sourceOrder'] = $order;
            $options['convertedItems'] = $order->items;
            $convertedItems = $order->items;
            $sourceCustId = $order->customer_id;
        } elseif ($request->filled('from_delivery_note')) {
            $deliveryNote = \App\Models\SalesDeliveryNote::with(['items.item.gstTax', 'customer', 'branch'])
                ->findOrFail($request->input('from_delivery_note'));
            $options['sourceDeliveryNote'] = $deliveryNote;
            $convertedItems = $deliveryNote->items->map(function ($dnItem) {
                return (object) [
                    'item_id' => $dnItem->item_id,
                    'item' => $dnItem->item,
                    'qty' => $dnItem->dispatched_qty,
                    'sell_price' => $dnItem->unit_price,
                    'mrp' => $dnItem->mrp ?? $dnItem->item->mrp,
                    'cost_at_sale' => $dnItem->cost_at_dispatch,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => (float) ($dnItem->item->gstTax?->igst ?? 0),
                    'gst_tax_amount' => 0,
                    'cgst_amount' => 0,
                    'sgst_amount' => 0,
                    'igst_amount' => 0,
                    'net_amount' => $dnItem->line_total,
                ];
            });
            $options['convertedItems'] = $convertedItems;
            $sourceCustId = $deliveryNote->customer_id;
        }

        $options = array_merge($options, $this->formOptions(null, $convertedItems, $sourceCustId));
        $options['nextBillNumber'] = $this->nextNumber();

        return view('sales.sales-bills.create', $options);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['bill_date']);

        if ($data['header']['posting_key'] ?? null) {
            $existing = SalesBill::where('posting_key', $data['header']['posting_key'])->first();
            if ($existing) {
                return redirect()->route('sales.sales-bills.index')->with('status', "Sales Bill {$existing->bill_number} created successfully.");
            }
        }

        $salesBill = DB::transaction(function () use ($data, $request) {
            $lines = $this->computeLines($data['items'], $data['header']);
            
            // If converted from delivery note, physical stock was already deducted at dispatch
            if (empty($data['header']['sales_delivery_note_id'])) {
                $this->assertStockAvailable($lines, $data['header']['branch_id']);
            }

            $totals = $this->computeTotals($lines, $data);

            $this->assertCreditLimit((int) $data['header']['customer_id'], $totals['total']);

            $salesBill = SalesBill::create(array_merge($data['header'], $totals, [
                'bill_number' => $this->nextNumber(),
            ]));

            $createdItems = $salesBill->items()->createMany($lines);

            // Stock posting: if converted from a SalesDeliveryNote, physical stock
            // was already dispatched. We only update cost_at_sale on bill items without
            // duplicating stock in stock_ledger.
            if (!empty($data['header']['sales_delivery_note_id'])) {
                $sdn = \App\Models\SalesDeliveryNote::with('items')->find($data['header']['sales_delivery_note_id']);
                if ($sdn) {
                    $sdn->update([
                        'status' => 'Invoiced',
                        'sales_bill_id' => $salesBill->id,
                    ]);
                    $sdnCosts = $sdn->items->pluck('cost_at_dispatch', 'item_id');
                    foreach ($createdItems as $item) {
                        $cost = $sdnCosts[$item->item_id] ?? 0;
                        if ($cost > 0) {
                            $item->update(['cost_at_sale' => $cost]);
                        }
                    }
                }
            } else {
                $this->postStock($createdItems, $salesBill);
            }

            $this->persistPayments($salesBill, $data, $totals['total']);
            $this->ledgerPosting->postSalesBill($salesBill);

            $this->loyaltyService->accruePointsForBill($salesBill);

            if ($request->filled('redeemed_loyalty_points') && (float) $request->input('redeemed_loyalty_points') > 0) {
                $this->loyaltyService->redeemPointsForBill(
                    $salesBill,
                    (float) $request->input('redeemed_loyalty_points'),
                    $request->filled('redeemed_loyalty_amount') ? (float) $request->input('redeemed_loyalty_amount') : null
                );
            }

            if ($request->filled('from_quotation_id')) {
                SalesQuotation::where('id', $request->input('from_quotation_id'))->update([
                    'status' => 'Converted',
                    'converted_sales_bill_id' => $salesBill->id,
                ]);
            }

            if ($request->filled('from_order_id')) {
                SalesOrder::where('id', $request->input('from_order_id'))->update([
                    'status' => 'Converted',
                    'converted_sales_bill_id' => $salesBill->id,
                ]);
            }

            return $salesBill;
        });

        return redirect()->route('sales.sales-bills.index')->with('status', "Sales Bill {$salesBill->bill_number} created successfully.");
    }

    public function edit(SalesBill $salesBill)
    {
        $salesBill->load(['items.item.gstTax', 'customer']);

        return view('sales.sales-bills.edit', array_merge(['salesBill' => $salesBill], $this->formOptions($salesBill, $salesBill->items, $salesBill->customer_id)));
    }

    public function show(SalesBill $salesBill)
    {
        $salesBill->load(['customer', 'branch', 'items.item.gstTax', 'payments.tenderType', 'payments.tenderTypeValue']);

        return view('sales.sales-bills.show', compact('salesBill'));
    }

    public function receipt(SalesBill $salesBill)
    {
        $salesBill->load(['customer', 'branch', 'items.item.gstTax', 'payments.tenderType', 'payments.tenderTypeValue']);

        return view('sales.sales-bills.receipt', compact('salesBill'));
    }

    public function update(Request $request, SalesBill $salesBill)
    {
        $salesBill->assertEditable();

        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['bill_date']);
        $oldCustomerId = $salesBill->customer_id;
        $oldTotal = (float) $salesBill->total;

        DB::transaction(function () use ($data, $salesBill, $oldCustomerId, $oldTotal) {
            $this->stockLedger->reverseByReference(SalesBill::class, $salesBill->id);

            $lines = $this->computeLines($data['items'], $data['header']);
            $this->assertStockAvailable($lines, $data['header']['branch_id']);
            $totals = $this->computeTotals($lines, $data);

            $this->assertCreditLimit((int) $data['header']['customer_id'], $totals['total'], $oldCustomerId, $oldTotal);

            $salesBill->update(array_merge($data['header'], $totals));
            $salesBill->items()->delete();
            $createdItems = $salesBill->items()->createMany($lines);

            $this->postStock($createdItems, $salesBill);
            $this->persistPayments($salesBill, $data, $totals['total']);
            $this->ledgerPosting->postSalesBill($salesBill);
        });

        return redirect()->route('sales.sales-bills.index')->with('status', "Sales Bill {$salesBill->bill_number} updated successfully.");
    }

    public function destroy(SalesBill $salesBill)
    {
        // No assertEditable() guard here: destroy() already IS the cancel/reversal action.
        $oldValues = $salesBill->only(['bill_number', 'bill_date', 'customer_id', 'branch_id', 'total', 'status']);

        DB::transaction(function () use ($salesBill, $oldValues) {
            if ($salesBill->sales_delivery_note_id) {
                $sdn = \App\Models\SalesDeliveryNote::find($salesBill->sales_delivery_note_id);
                if ($sdn && $sdn->status === 'Invoiced') {
                    $sdn->update([
                        'status' => 'Dispatched',
                        'sales_bill_id' => null,
                    ]);
                }
            } else {
                $this->stockLedger->reverseByReference(SalesBill::class, $salesBill->id);
            }

            $this->ledgerPosting->reverse(SalesBill::class, $salesBill->id);
            $this->loyaltyService->reverseBillPoints($salesBill);
            $this->auditLogger->log('cancel', $salesBill, $oldValues, null);
            $salesBill->delete();
        });

        return redirect()->route('sales.sales-bills.index')->with('status', 'Sales Bill deleted and journal reversed.');
    }

    public function customerLoyalty(Customer $customer)
    {
        return response()->json($this->loyaltyService->getCustomerLoyalty($customer));
    }

    public function customerInvoices(Customer $customer)
    {
        $bills = SalesBill::where('customer_id', $customer->id)
            ->orderBy('bill_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get(['id', 'bill_number', 'bill_date', 'total', 'invoice_type', 'status'])
            ->map(function ($bill) {
                return [
                    'id' => $bill->id,
                    'bill_number' => $bill->bill_number,
                    'bill_date' => $bill->bill_date ? $bill->bill_date->format('d-m-Y h:i A') : '',
                    'total' => (float) $bill->total,
                    'invoice_type' => $bill->invoice_type,
                    'status' => $bill->status,
                    'view_url' => route('sales.sales-bills.show', $bill),
                    'edit_url' => route('sales.sales-bills.edit', $bill),
                    'print_url' => route('sales.sales-bills.receipt', $bill),
                ];
            });

        return response()->json([
            'customer_name' => $customer->name,
            'customer_mobile' => $customer->mobile ?? '',
            'invoices' => $bills,
        ]);
    }

    /**
     * Posts the SALE movement for each line and snapshots the cost the ledger actually
     * released (the item's moving-average cost at this moment) as cost_at_sale — this is
     * what makes gross-profit reporting immune to later purchases changing the average.
     */
    private function postStock($createdItems, SalesBill $salesBill): void
    {
        foreach ($createdItems as $itemLine) {
            $ledgerRow = $this->stockLedger->post(
                itemId: $itemLine->item_id,
                branchId: $salesBill->branch_id,
                movementType: 'SALE',
                qtyDelta: -1 * (float) $itemLine->qty,
                unitCost: null,
                referenceType: SalesBill::class,
                referenceId: $salesBill->id,
                documentDate: $salesBill->bill_date->toDateString(),
                expDate: $itemLine->exp_date?->toDateString(),
            );

            $itemLine->update(['cost_at_sale' => $ledgerRow->unit_cost]);
        }
    }

    /**
     * Purely additive: a request with no "payments" key behaves exactly as it always
     * has — payment_type stays a free string, no till linkage, no sales_bill_payments
     * rows, LedgerPostingService keeps debiting the customer ledger as before. Only a
     * request that opts in by sending "payments" gets the new split-tender behavior.
     */
    private function persistPayments(SalesBill $salesBill, array $data, float $total): void
    {
        $payments = $data['payments'] ?? [];

        if (empty($payments)) {
            return;
        }

        $sum = round(collect($payments)->sum('amount'), 2);
        $diff = round($total - $sum, 2);
        if (abs($diff) <= 0.05 && $diff != 0 && count($payments) > 0) {
            $payments[0]['amount'] = round((float) $payments[0]['amount'] + $diff, 2);
            $sum = round(collect($payments)->sum('amount'), 2);
        }

        if (abs($sum - round($total, 2)) > 0.01) {
            throw ValidationException::withMessages([
                'payments' => "Payment total ({$sum}) does not match the bill total ({$total}).",
            ]);
        }

        $salesBill->payments()->delete();
        $salesBill->payments()->createMany(collect($payments)->map(fn ($p) => [
            'tender_type_id' => $p['tender_type_id'],
            'tender_type_value_id' => $p['tender_type_value_id'] ?? null,
            'amount' => $p['amount'],
        ])->all());
    }

    /**
     * Checks the credit-limit delta this save would introduce, not the raw new total —
     * on an edit, the old total is already reflected in the customer's live ledger
     * balance, so only the CHANGE matters. If the customer was switched, the old
     * customer's contribution is removed (a negative delta, never blocking) and the new
     * customer is checked against the full new total (their delta from zero).
     */
    private function assertCreditLimit(int $newCustomerId, float $newTotal, ?int $oldCustomerId = null, float $oldTotal = 0.0): void
    {
        if ($oldCustomerId !== null && $oldCustomerId === $newCustomerId) {
            $this->creditLimitGuard->assertWithinLimit(Customer::findOrFail($newCustomerId), $newTotal - $oldTotal);

            return;
        }

        if ($oldCustomerId !== null) {
            $this->creditLimitGuard->assertWithinLimit(Customer::findOrFail($oldCustomerId), -$oldTotal);
        }

        $this->creditLimitGuard->assertWithinLimit(Customer::findOrFail($newCustomerId), $newTotal);
    }

    private function assertStockAvailable(array $lines, int $branchId): void
    {
        // Group by item_id and sum total requested qty per item
        // This prevents the same item appearing in multiple rows with combined qty > stock
        $totalQtyByItem = [];
        foreach ($lines as $line) {
            $itemId = $line['item_id'];
            $totalQtyByItem[$itemId] = ($totalQtyByItem[$itemId] ?? 0) + (float) $line['qty'];
        }

        foreach ($totalQtyByItem as $itemId => $totalRequested) {
            $item = Item::find($itemId);
            if ($item->allow_negative_stock) {
                continue;
            }

            $available = (float) (ItemStock::where('item_id', $itemId)
                ->where('branch_id', $branchId)
                ->value('quantity') ?? 0);

            if ($totalRequested > $available) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for \"{$item->name}\": available {$available}, requested {$totalRequested} (across all rows).",
                ]);
            }
        }
    }

    private function nextNumber(): string
    {
        $maxId = (int) (SalesBill::max('id') ?? 0);
        do {
            $maxId++;
            $num = 'SB'.str_pad((string) $maxId, 6, '0', STR_PAD_LEFT);
        } while (SalesBill::where('bill_number', $num)->exists());

        return $num;
    }

    public function itemList(Request $request)
    {
        $branchId = (int) ($request->input('branch_id') ?: session('active_branch_id', auth()->user()?->branch_id ?? 3));
        $search   = trim((string) $request->input('search', ''));
        $expiry   = trim((string) $request->input('expiry', ''));
        $code     = trim((string) $request->input('code', ''));

        // Require at least 1 character to avoid loading 8000+ items on every open
        $hasFilter = $search !== '' || $code !== '' || $expiry !== '';
        if (! $hasFilter) {
            return response()->json(['items' => [], 'hint' => 'Type to search items\u2026']);
        }

        // --- Single optimised query: items LEFT JOINed with stock & earliest expiry ---
        $limit  = 100;
        $where  = ['i.status = 1'];
        $params = [$branchId, $branchId];

        $orderSql    = 'i.name ASC';
        $orderParams = [];

        if ($search !== '') {
            $sWild   = "%{$search}%";
            $sExact  = $search;
            $sPrefix = "{$search}%";

            // Barcode (ean_upc_code) only matches exact or prefix — never substring in middle of 13-digit barcode!
            // Item code matches exact or prefix
            // Item name matches substring
            $where[] = '(i.name LIKE ? OR i.item_code = ? OR i.item_code LIKE ? OR i.ean_upc_code = ? OR i.ean_upc_code LIKE ?)';
            $params  = array_merge($params, [$sWild, $sExact, $sPrefix, $sExact, $sPrefix]);

            // Rank exact code / barcode match first, then prefix, then name
            $orderSql = "
                CASE
                    WHEN i.item_code = ? THEN 1
                    WHEN i.ean_upc_code = ? THEN 2
                    WHEN i.item_code LIKE ? THEN 3
                    WHEN i.ean_upc_code LIKE ? THEN 4
                    WHEN i.name LIKE ? THEN 5
                    ELSE 6
                END ASC,
                i.name ASC
            ";
            $orderParams = [$sExact, $sExact, $sPrefix, $sPrefix, $sPrefix];
        }

        if ($code !== '') {
            $cExact  = $code;
            $cPrefix = "{$code}%";
            $where[] = '(i.item_code = ? OR i.item_code LIKE ? OR i.ean_upc_code = ? OR i.ean_upc_code LIKE ?)';
            $params  = array_merge($params, [$cExact, $cPrefix, $cExact, $cPrefix]);

            if ($search === '') {
                $orderSql = "
                    CASE
                        WHEN i.item_code = ? THEN 1
                        WHEN i.ean_upc_code = ? THEN 2
                        WHEN i.item_code LIKE ? THEN 3
                        WHEN i.ean_upc_code LIKE ? THEN 4
                        ELSE 5
                    END ASC,
                    i.name ASC
                ";
                $orderParams = [$cExact, $cExact, $cPrefix, $cPrefix];
            }
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT
                i.id,
                i.name,
                COALESCE(i.item_code, '')  AS item_code,
                COALESCE(i.ean_upc_code, '') AS ean_upc_code,
                COALESCE(st.quantity, 0)   AS qty,
                COALESCE(
                    NULLIF(ei.sell_price, 0),
                    NULLIF(i.sell_price, 0),
                    0
                )                          AS sell_price,
                COALESCE(
                    NULLIF(ei.mrp, 0),
                    NULLIF(i.mrp, 0),
                    0
                )                          AS mrp,
                ei.exp_date,
                COALESCE(gt.percentage, 0) AS gst_percent
            FROM items i
            LEFT JOIN item_stocks st
                   ON st.item_id = i.id AND st.branch_id = ?
            LEFT JOIN (
                SELECT pi2.item_id,
                       MIN(pi2.exp_date) AS exp_date,
                       SUBSTRING_INDEX(GROUP_CONCAT(pi2.sell_price ORDER BY pi2.exp_date ASC SEPARATOR ','), ',', 1) AS sell_price,
                       SUBSTRING_INDEX(GROUP_CONCAT(pi2.mrp        ORDER BY pi2.exp_date ASC SEPARATOR ','), ',', 1) AS mrp
                FROM purchase_invoice_items pi2
                INNER JOIN purchase_invoices pih
                        ON pih.id = pi2.purchase_invoice_id AND pih.branch_id = ?
                WHERE pi2.exp_date IS NOT NULL
                  AND CAST(pi2.exp_date AS CHAR) NOT IN ('', '0000-00-00')
                GROUP BY pi2.item_id
            ) ei ON ei.item_id = i.id
            LEFT JOIN gst_taxes gt ON gt.id = i.gst_tax_id
            {$whereClause}
            ORDER BY {$orderSql}
            LIMIT {$limit}
        ";

        $finalParams = array_merge($params, $orderParams);
        $rows = \Illuminate\Support\Facades\DB::select($sql, $finalParams);

        // Fallback: for rows without branch-specific expiry, try all branches
        $noExpIds = collect($rows)->filter(fn ($r) => empty($r->exp_date))->pluck('id')->all();
        $expiryFallback = [];
        if (! empty($noExpIds)) {
            $ph = implode(',', array_fill(0, count($noExpIds), '?'));
            $fbSql = "
                SELECT pi2.item_id,
                       MIN(pi2.exp_date) AS exp_date,
                       SUBSTRING_INDEX(GROUP_CONCAT(pi2.sell_price ORDER BY pi2.exp_date ASC SEPARATOR ','), ',', 1) AS sell_price,
                       SUBSTRING_INDEX(GROUP_CONCAT(pi2.mrp        ORDER BY pi2.exp_date ASC SEPARATOR ','), ',', 1) AS mrp
                FROM purchase_invoice_items pi2
                WHERE pi2.item_id IN ({$ph})
                  AND pi2.exp_date IS NOT NULL
                   AND CAST(pi2.exp_date AS CHAR) NOT IN ('', '0000-00-00')
                GROUP BY pi2.item_id
            ";
            foreach (\Illuminate\Support\Facades\DB::select($fbSql, $noExpIds) as $fb) {
                $expiryFallback[$fb->item_id] = $fb;
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $expRaw = $row->exp_date ?? null;

            // Use cross-branch fallback expiry if main query returned null
            if (empty($expRaw) && isset($expiryFallback[$row->id])) {
                $fb     = $expiryFallback[$row->id];
                $expRaw = $fb->exp_date;
                if ((float) ($fb->sell_price ?? 0) > 0) $row->sell_price = $fb->sell_price;
                if ((float) ($fb->mrp ?? 0) > 0)        $row->mrp        = $fb->mrp;
            }

            // Normalise expiry date
            $exp = null;
            if (! empty($expRaw) && $expRaw !== '0000-00-00') {
                try {
                    $exp = \Carbon\Carbon::parse($expRaw)->format('Y-m-d');
                } catch (\Exception $e) {
                    $exp = substr((string) $expRaw, 0, 10) ?: null;
                }
            }

            // Apply expiry filter if requested
            if ($expiry !== '' && (! $exp || strpos($exp, $expiry) === false)) continue;

            $result[] = [
                'id'          => (int) $row->id,
                'name'        => $row->name,
                'code'        => $row->item_code ?: ($row->ean_upc_code ?: ''),
                'exp_date'    => $exp,
                'qty'         => (float) $row->qty,
                'sell_price'  => (float) $row->sell_price,
                'mrp'         => (float) $row->mrp,
                'gst_percent' => (float) $row->gst_percent,
            ];
        }

        return response()->json(['items' => $result]);
    }

    public function lookupItem(Request $request)
    {
        $itemId = $request->input('item_id');
        $query = trim((string) $request->input('query', ''));
        $branchId = (int) ($request->input('branch_id') ?: session('active_branch_id', auth()->user()?->branch_id ?? 3));

        $item = null;
        if (! empty($itemId)) {
            $item = Item::where('status', true)->with('gstTax:id,percentage')->find($itemId);
        }

        if (! $item && $query !== '') {
            // Check exact item_code or ean_upc_code first (crucial for barcode / code inputs)
            $item = Item::where('status', true)
                ->where(function ($q) use ($query) {
                    $q->where('item_code', $query)
                      ->orWhere('ean_upc_code', $query);
                })
                ->with('gstTax:id,percentage')
                ->first();

            // Check primary key if numeric
            if (! $item && is_numeric($query)) {
                $item = Item::where('status', true)->with('gstTax:id,percentage')->find($query);
            }

            // Check item name
            if (! $item) {
                $item = Item::where('status', true)
                    ->with('gstTax:id,percentage')
                    ->where('name', 'like', "%{$query}%")
                    ->first();
            }
        }

        if (! $item) {
            return response()->json(['found' => false]);
        }

        // Get total available stock in the selected branch
        $stock = (float) (ItemStock::where('item_id', $item->id)
            ->where('branch_id', $branchId)
            ->value('quantity') ?? 0);

        // Fetch purchase batches directly from PurchaseInvoiceItem (purchase se)
        // Check for selected branch first
        $piItems = \App\Models\PurchaseInvoiceItem::with('purchaseInvoice')
            ->where('item_id', $item->id)
            ->whereNotNull('exp_date')
            ->where('exp_date', '!=', '')
            ->where('exp_date', '!=', '0000-00-00')
            ->whereHas('purchaseInvoice', fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('exp_date', 'asc')
            ->get();

        // If no purchase records for this specific branch, check across all branches
        if ($piItems->isEmpty()) {
            $piItems = \App\Models\PurchaseInvoiceItem::with('purchaseInvoice')
                ->where('item_id', $item->id)
                ->whereNotNull('exp_date')
                ->where('exp_date', '!=', '')
                ->where('exp_date', '!=', '0000-00-00')
                ->orderBy('exp_date', 'asc')
                ->get();
        }

        // Also check OpeningStockItem if still empty
        if ($piItems->isEmpty()) {
            $piItems = \App\Models\OpeningStockItem::with('openingStock')
                ->where('item_id', $item->id)
                ->whereNotNull('exp_date')
                ->where('exp_date', '!=', '')
                ->where('exp_date', '!=', '0000-00-00')
                ->orderBy('exp_date', 'asc')
                ->get();
        }

        // Also check StockLedger if still empty
        if ($piItems->isEmpty()) {
            $piItems = \App\Models\StockLedger::where('item_id', $item->id)
                ->whereNotNull('exp_date')
                ->where('exp_date', '!=', '')
                ->where('exp_date', '!=', '0000-00-00')
                ->orderBy('exp_date', 'asc')
                ->get();
        }

        $grouped = [];
        foreach ($piItems as $row) {
            $exp = null;
            try {
                $exp = $row->exp_date ? \Carbon\Carbon::parse($row->exp_date)->format('Y-m-d') : null;
            } catch (\Exception $e) {
                $exp = is_string($row->exp_date) ? substr($row->exp_date, 0, 10) : null;
            }
            if (! $exp) continue;

            $sell = (float) (($row->sell_price ?? 0) > 0 ? $row->sell_price : ($item->sell_price ?? 0));
            $mrp = (float) (($row->mrp ?? 0) > 0 ? $row->mrp : ($item->mrp ?? 0));
            $qty = (float) ($row->qty ?? $row->qty_in ?? 0);

            if (! isset($grouped[$exp])) {
                $grouped[$exp] = [
                    'productname' => $item->name,
                    'code' => $item->item_code ?: ($item->ean_upc_code ?: ''),
                    'exp_date' => $exp,
                    'qty' => $qty,
                    'sell_price' => $sell,
                    'mrp' => $mrp,
                    'source' => 'purchase',
                ];
            } else {
                $grouped[$exp]['qty'] += $qty;
                if ($sell > 0) $grouped[$exp]['sell_price'] = $sell;
                if ($mrp > 0) $grouped[$exp]['mrp'] = $mrp;
            }
        }

        $batches = array_values($grouped);

        return response()->json([
            'found' => true,
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'item_code' => $item->item_code,
                'ean_upc_code' => $item->ean_upc_code,
                'cost_price' => (float) ($item->cost_price ?? 0),
                'sell_price' => (float) ($item->sell_price ?? 0),
                'mrp' => (float) ($item->mrp ?? 0),
                'stock' => $stock,
                'gst_percent' => (float) ($item->gstTax?->percentage ?? 0),
                'batch_expiry_details' => $item->batch_expiry_details ?? 'Not Required',
            ],
            'batches' => $batches,
        ]);
    }

    public function customerSearch(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $customers = Customer::where('status', true)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('mobile', 'like', "%{$q}%")
                        ->orWhere('customer_code', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'mobile'])
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'text' => $c->mobile ? "{$c->name} ({$c->mobile})" : $c->name,
                    'mobile' => $c->mobile ?? '',
                ];
            });

        return response()->json(['results' => $customers]);
    }

    private function formOptions(?SalesBill $salesBill = null, $convertedItems = null, ?int $explicitCustomerId = null): array
    {
        // 1. Initial customers (top 20 active plus selected customer if editing or converting)
        $selectedCustId = old('customer_id', $explicitCustomerId ?? $salesBill?->customer_id);
        $customers = Customer::where('status', true)
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'mobile'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->mobile ? "{$c->name} ({$c->mobile})" : $c->name]);

        if ($selectedCustId && ! isset($customers[$selectedCustId])) {
            $selCust = Customer::find($selectedCustId);
            if ($selCust) {
                $customers->put($selCust->id, $selCust->mobile ? "{$selCust->name} ({$selCust->mobile})" : $selCust->name);
            }
        }

        // 2. Only load items that are currently in the bill (edit/convert mode), NOT all 8,597 items!
        $existingItemIds = collect($salesBill?->items ?? ($convertedItems ?? []))->pluck('item_id')->filter()->unique();
        $items = $existingItemIds->isNotEmpty()
            ? Item::whereIn('id', $existingItemIds)->with('gstTax:id,percentage')->get([
                'id', 'name', 'item_code', 'ean_upc_code', 'cost_price', 'sell_price', 'mrp', 'gst_tax_id', 'batch_expiry_details'
            ])
            : collect();

        $tenderTypes = TenderType::with(['values' => fn ($q) => $q->where('status', true)])->where('status', true)->orderBy('id')->get();
        if ($tenderTypes->isEmpty()) {
            (new \Database\Seeders\TenderTypeSeeder())->run();
            $tenderTypes = TenderType::with(['values' => fn ($q) => $q->where('status', true)])->where('status', true)->orderBy('id')->get();
        }

        return [
            'customers'   => $customers,
            'branches'    => Branch::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'items'       => $items,
            'tenderTypes' => $tenderTypes,
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
            'total_qty' => $collection->sum('qty'),
            'total' => round($collection->sum('net_amount') + $roundOff + $totalExtraCess + $gstCalamityCess, 2),
        ];
    }

    private function validateData(Request $request): array
    {
        // Filter out empty rows (where item_id is missing or qty <= 0) before validation
        $rawItems = $request->input('items', []);
        $filteredItems = collect($rawItems)->filter(function ($item) {
            return !empty($item['item_id']) && (float)($item['qty'] ?? 0) > 0;
        })->values()->all();
        $request->merge(['items' => $filteredItems]);

        $now = now()->addMinutes(2)->format('Y-m-d H:i:s');
        $headerRules = [
            'bill_number' => ['nullable', 'string', 'max:100'],
            'bill_date' => ['required', 'date', "before_or_equal:{$now}"],
            'customer_id' => ['required', 'exists:customers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'sales_delivery_note_id' => ['nullable', 'exists:sales_delivery_notes,id'],
            'invoice_type' => ['required', 'in:Retail Invoice,Tax Invoice,Exempted'],
            'delivery_type' => ['required', 'string', 'max:255'],
            'delivery_time' => ['nullable', 'date_format:H:i'],
            'sales_type' => ['required', 'in:Local,Interstate'],
            'payment_type' => ['nullable', 'string', 'max:255'],
            'till_session_id' => ['nullable', 'exists:till_sessions,id'],
            'round_off' => ['nullable', 'numeric'],
            'total_extra_cess' => ['nullable', 'numeric', 'min:0'],
            'gst_calamity_cess' => ['nullable', 'numeric', 'min:0'],
            'total_weight' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
            'posting_key' => ['nullable', 'string', 'max:100'],
        ];

        $headerMessages = [
            'bill_date.before_or_equal' => 'Future date and time is not allowed for Bill Date.',
        ];

        $dynamicService = app(\App\Services\DynamicValidationService::class);
        $dynamicService->applyTo('sales_bills', $headerRules, $headerMessages);

        $header = $request->validate($headerRules, $headerMessages);

        $configs = $dynamicService->getConfigsForModule('sales_bills');

        if (!empty($header['bill_number']) && ($configs['bill_number']->is_unique ?? true)) {
            $duplicateBill = SalesBill::where('bill_number', $header['bill_number'])->exists();
            if ($duplicateBill) {
                $msg = !empty($configs['bill_number']->custom_error_message)
                    ? $configs['bill_number']->custom_error_message
                    : "Bill Number '{$header['bill_number']}' already exists.";
                throw ValidationException::withMessages([
                    'bill_number' => $msg,
                ]);
            }
        }

        if ($request->isMethod('post') && !empty($header['customer_id'])) {
            $recentDuplicate = SalesBill::where('customer_id', $header['customer_id'])
                ->where('branch_id', $header['branch_id'])
                ->where('created_at', '>=', now()->subSeconds(30))
                ->whereHas('items', function ($iq) use ($filteredItems) {
                    if (!empty($filteredItems[0]['item_id'])) {
                        $iq->where('item_id', $filteredItems[0]['item_id']);
                    }
                })
                ->exists();
            if ($recentDuplicate) {
                throw ValidationException::withMessages([
                    'customer_id' => 'A matching sales bill was just submitted moments ago. Duplicate submission prevented.',
                ]);
            }
        }

        $header['bill_date'] = \Illuminate\Support\Carbon::parse($header['bill_date'])->format('Y-m-d H:i:s');

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

        $paymentsValidated = $request->validate([
            'payments' => ['nullable', 'array'],
            'payments.*.tender_type_id' => ['required_with:payments', 'exists:tender_types,id'],
            'payments.*.tender_type_value_id' => ['nullable', 'exists:tender_type_values,id'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
        ]);

        return ['header' => $header, 'items' => $validated['items'], 'payments' => $paymentsValidated['payments'] ?? []];
    }
}
