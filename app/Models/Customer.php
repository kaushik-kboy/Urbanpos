<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory, \App\Traits\HasCustomFields;

    protected $fillable = [
        'title', 'name', 'customer_category_id', 'customer_code', 'sales_type', 'payment_mode',
        'credit_limit', 'credit_balance', 'monthly_credit_balance', 'credit_days', 'branch_id',
        'status', 'sales_formula', 'gst_type', 'sms_consent',
        'address1', 'area_id', 'city', 'state', 'country', 'postal_code', 'std_code', 'phone',
        'email', 'remarks', 'gst_no', 'aadhar_no', 'pan_no', 'mobile',
        'gender', 'exempted_reason', 'customer_type',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'credit_balance' => 'decimal:2',
        'monthly_credit_balance' => 'decimal:2',
        'status' => 'boolean',
        'sms_consent' => 'boolean',
    ];

    /**
     * Ids of active customers matching a picker search term, best-effort "first $limit by name".
     *
     * Every query here is index-only (covering) so no row is fetched until the final id list is known;
     * the old single `select * ... (name LIKE OR mobile LIKE OR code LIKE OR id IN pets) ORDER BY name`
     * did a primary-key lookup per scanned row (1.07 s at 92K customers for a rare fragment).
     *  1. name: (status,name) covering index, name order, stops at $limit  -> common terms exit early.
     *  2. only if name matches did not fill $limit: mobile / customer_code / pet name substring scans.
     * status is deliberately NOT in the mobile/code probes (it would steer MySQL to the wide status index);
     * it is applied by the caller's final fetch.
     */
    public static function pickerMatchIds(string $term, int $limit = 30, int $probeCap = 500): array
    {
        $like = "%{$term}%";

        $ids = static::query()->where('status', true)->where('name', 'like', $like)
            ->orderBy('name')->limit($limit)->pluck('id')->all();
        if (count($ids) >= $limit) {
            return $ids;
        }

        $more = static::query()->where('mobile', 'like', $like)->limit($probeCap)->pluck('id')
            ->merge(static::query()->where('phone', 'like', $like)->limit($probeCap)->pluck('id'))
            ->merge(\Illuminate\Support\Facades\DB::table('customer_pets')->where('name', 'like', $like)->limit($probeCap)->pluck('customer_id'));

        return collect($ids)->merge($more)->unique()->values()->all();
    }

    public function isCreditAllowed(): bool
    {
        $mode = strtolower(trim((string) $this->payment_mode));
        return !in_array($mode, ['no credit', 'cash only'], true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'customer_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function pets(): HasMany
    {
        return $this->hasMany(CustomerPet::class);
    }

    public function ledger(): HasOne
    {
        return $this->hasOne(Ledger::class);
    }

    public function loyaltyPoints(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyPoint::class);
    }

    public function loyaltyBalance(): float
    {
        $earned = (float) $this->loyaltyPoints()
            ->whereIn('type', ['Earned', 'Adjustment_Add'])
            ->sum('points');

        $deducted = (float) $this->loyaltyPoints()
            ->whereIn('type', ['Redeemed', 'Adjustment_Deduct'])
            ->sum('points');

        $reversals = (float) $this->loyaltyPoints()
            ->where('type', 'Reversal')
            ->sum('points');

        return (float) max(0.0, round($earned - $deducted + $reversals, 2));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->mobile ? "{$this->name} ({$this->mobile})" : $this->name;
    }

    /**
     * Calculate outstanding pending balance for this customer.
     * Formula: Sum(Sales Bills Total) - Sum(POS Non-Credit Payments) - Sum(Active Settlements)
     */
    public function getOutstandingBalance(?int $branchId = null): array
    {
        $balances = static::getBatchOutstandingBalances([$this->id], $branchId);
        return $balances[$this->id] ?? [
            'amount' => 0.0,
            'formatted' => '₹ 0.00',
            'unpaid_bills_count' => 0,
            'is_pending' => false,
        ];
    }

    /**
     * Batch calculation of outstanding pending balances for multiple customer IDs in a single query.
     */
    public static function getBatchOutstandingBalances(array $customerIds, ?int $branchId = null): array
    {
        $customerIds = array_values(array_filter(array_unique(array_map('intval', $customerIds))));
        if (empty($customerIds)) {
            return [];
        }

        $query = SalesBill::with([
            'payments.tenderType',
            'settlementItems.settlement',
        ])
            ->whereIn('customer_id', $customerIds)
            ->where('status', '!=', 'Cancelled');

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', (int) $branchId);
        }

        $bills = $query->get(['id', 'customer_id', 'branch_id', 'total', 'payment_type', 'status']);
        $billsByCustomer = $bills->groupBy('customer_id');

        $results = [];
        foreach ($customerIds as $cId) {
            $custBills = $billsByCustomer->get($cId, collect());
            $totalOutstanding = 0.0;
            $unpaidCount = 0;

            foreach ($custBills as $sb) {
                $posImmediatePaid = 0.0;
                if ($sb->payments->isNotEmpty()) {
                    $posImmediatePaid = (float) $sb->payments
                        ->filter(fn ($p) => ($p->tenderType?->type ?? '') !== 'Credit')
                        ->sum('amount');
                } elseif (!empty($sb->payment_type) && strtolower($sb->payment_type) !== 'credit' && strtolower($sb->payment_type) !== 'none') {
                    $posImmediatePaid = (float) $sb->total;
                }

                $settledSum = (float) $sb->settlementItems
                    ->filter(fn ($si) => ($si->settlement?->status ?? 'Active') === 'Active')
                    ->sum(fn ($si) => (float) $si->settled_amount + (float) $si->discount_amount);

                $paidForBill = round($posImmediatePaid + $settledSum, 2);
                $dueForBill = round((float) $sb->total - $paidForBill, 2);

                if ($dueForBill > 0.01) {
                    $totalOutstanding += $dueForBill;
                    $unpaidCount++;
                }
            }

            $amount = max(0.0, round($totalOutstanding, 2));
            $formatted = '₹ ' . number_format($amount, 2);
            $results[$cId] = [
                'amount' => $amount,
                'formatted' => $formatted,
                'outstanding_amount' => $amount,
                'outstanding_formatted' => $formatted,
                'unpaid_bills_count' => $unpaidCount,
                'is_pending' => $amount > 0.01,
            ];
        }

        return $results;
    }

    public static function options(int $limit = 50): \Illuminate\Support\Collection
    {
        $query = static::where('status', true)->orderBy('name');
        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get(['id', 'name', 'mobile'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->mobile ? "{$c->name} ({$c->mobile})" : $c->name]);
    }

    protected static function booted(): void
    {
        static::created(function (Customer $customer) {
            Ledger::create([
                'name' => $customer->name,
                'ledger_group' => 'Sundry Debtors',
                'customer_id' => $customer->id,
            ]);
        });
    }
}
