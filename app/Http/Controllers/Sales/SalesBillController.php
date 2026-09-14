<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Services\Accounting\CreditLimitGuard;
use App\Services\Accounting\FinancialYearGuard;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\StockLedgerService;
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
    ) {
    }

    public function index()
    {
        $salesBills = SalesBill::with(['customer', 'branch'])->latest('bill_date')->paginate(20);

        return view('sales.sales-bills.index', compact('salesBills'));
    }

    public function create()
    {
        return view('sales.sales-bills.create', $this->formOptions());
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

        $salesBill = DB::transaction(function () use ($data) {
            $lines = $this->computeLines($data['items'], $data['header']);
            $this->assertStockAvailable($lines, $data['header']['branch_id']);
            $totals = $this->computeTotals($lines, $data);

            $this->assertCreditLimit((int) $data['header']['customer_id'], $totals['total']);

            $salesBill = SalesBill::create(array_merge($data['header'], $totals, [
                'bill_number' => $this->nextNumber(),
            ]));

            $createdItems = $salesBill->items()->createMany($lines);
            $this->postStock($createdItems, $salesBill);
            $this->persistPayments($salesBill, $data, $totals['total']);
            $this->ledgerPosting->postSalesBill($salesBill);

            return $salesBill;
        });

        return redirect()->route('sales.sales-bills.index')->with('status', "Sales Bill {$salesBill->bill_number} created successfully.");
    }

    public function edit(SalesBill $salesBill)
    {
        $salesBill->load('items');

        return view('sales.sales-bills.edit', array_merge(['salesBill' => $salesBill], $this->formOptions()));
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
            $this->stockLedger->reverseByReference(SalesBill::class, $salesBill->id);
            $this->ledgerPosting->reverse(SalesBill::class, $salesBill->id);
            $this->auditLogger->log('cancel', $salesBill, $oldValues, null);
            $salesBill->delete();
        });

        return redirect()->route('sales.sales-bills.index')->with('status', 'Sales Bill deleted and stock restored.');
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
        foreach ($lines as $line) {
            $item = Item::find($line['item_id']);
            if ($item->allow_negative_stock) {
                continue;
            }

            $available = (float) (ItemStock::where('item_id', $line['item_id'])->where('branch_id', $branchId)->value('quantity') ?? 0);
            if ($line['qty'] > $available) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for \"{$item->name}\": available {$available}, requested {$line['qty']}.",
                ]);
            }
        }
    }

    private function nextNumber(): string
    {
        $next = (SalesBill::max('id') ?? 0) + 1;

        return 'SB'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function formOptions(): array
    {
        return [
            'customers' => Customer::orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'items' => Item::orderBy('name')->pluck('name', 'id'),
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

            $tax = $this->taxEngine->calculate($qty, $sellPrice, $item, $discPercent, $discAmount, 0.0, $isInterstate);

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
        $header = $request->validate([
            'bill_date' => ['required', 'date'],
            'customer_id' => ['required', 'exists:customers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
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
        ]);

        $header['bill_date'] = $this->normalizeDate($header['bill_date']);

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
