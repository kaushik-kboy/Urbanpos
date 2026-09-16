<?php

namespace App\Services\Loyalty;

use App\Models\Customer;
use App\Models\CustomerLoyaltyPoint;
use App\Models\LoyaltyProgram;
use App\Models\SalesBill;

class LoyaltyService
{
    public function getActiveProgram(): ?LoyaltyProgram
    {
        return LoyaltyProgram::active()->with('rules')->first();
    }

    public function getCustomerLoyalty(Customer|int $customer): array
    {
        if (is_numeric($customer)) {
            $customer = Customer::with('category')->find($customer);
        }

        if (! $customer) {
            return [
                'enable_loyalty' => false,
                'balance_points' => 0.0,
                'rupee_value' => 0.0,
                'can_redeem' => false,
                'min_points_redeem' => 50,
                'amount_per_point' => 1.0,
            ];
        }

        $enableLoyalty = (bool) ($customer->category?->enable_loyalty ?? false);
        $balance = $customer->loyaltyBalance();
        $program = $this->getActiveProgram();

        $minPoints = $program ? $program->min_points_redeem : 50;
        $amountPerPoint = $program ? (float) $program->amount_per_point : 1.0;
        $rupeeValue = round($balance * $amountPerPoint, 2);

        return [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'enable_loyalty' => $enableLoyalty,
            'balance_points' => $balance,
            'rupee_value' => $rupeeValue,
            'can_redeem' => ($enableLoyalty && $balance >= $minPoints),
            'min_points_redeem' => $minPoints,
            'amount_per_point' => $amountPerPoint,
            'program_name' => $program?->name ?? 'Default Program',
        ];
    }

    public function accruePointsForBill(SalesBill $salesBill): ?CustomerLoyaltyPoint
    {
        $customer = $salesBill->customer;
        if (! $customer) {
            $customer = Customer::with('category')->find($salesBill->customer_id);
        }

        if (! $customer || ! ($customer->category?->enable_loyalty ?? false)) {
            return null;
        }

        $program = $this->getActiveProgram();
        if (! $program) {
            return null;
        }

        $points = $program->calculatePointsForBill((float) $salesBill->total);
        if ($points <= 0) {
            return null;
        }

        return CustomerLoyaltyPoint::create([
            'customer_id' => $customer->id,
            'sales_bill_id' => $salesBill->id,
            'type' => 'Earned',
            'points' => $points,
            'amount_value' => round($points * (float) $program->amount_per_point, 2),
            'remarks' => "Earned on Sales Bill #{$salesBill->bill_number}",
            'created_by' => auth()->id(),
        ]);
    }

    public function redeemPointsForBill(SalesBill $salesBill, float $points, ?float $amountValue = null): ?CustomerLoyaltyPoint
    {
        if ($points <= 0) {
            return null;
        }

        $program = $this->getActiveProgram();
        $amountPerPoint = $program ? (float) $program->amount_per_point : 1.0;

        if ($amountValue === null) {
            $amountValue = round($points * $amountPerPoint, 2);
        }

        return CustomerLoyaltyPoint::create([
            'customer_id' => $salesBill->customer_id,
            'sales_bill_id' => $salesBill->id,
            'type' => 'Redeemed',
            'points' => $points,
            'amount_value' => $amountValue,
            'remarks' => "Redeemed on Sales Bill #{$salesBill->bill_number}",
            'created_by' => auth()->id(),
        ]);
    }

    public function reverseBillPoints(SalesBill $salesBill): void
    {
        $existing = CustomerLoyaltyPoint::where('sales_bill_id', $salesBill->id)
            ->whereIn('type', ['Earned', 'Redeemed'])
            ->get();

        foreach ($existing as $trans) {
            if ($trans->type === 'Earned') {
                // Reversing earned points: negate points to subtract from customer
                CustomerLoyaltyPoint::create([
                    'customer_id' => $trans->customer_id,
                    'sales_bill_id' => $salesBill->id,
                    'type' => 'Reversal',
                    'points' => -1 * abs((float) $trans->points),
                    'amount_value' => $trans->amount_value,
                    'remarks' => "Reversal of points earned on cancelled bill #{$salesBill->bill_number}",
                    'created_by' => auth()->id(),
                ]);
            } elseif ($trans->type === 'Redeemed') {
                // Reversing redeemed points: positive points to refund to customer
                CustomerLoyaltyPoint::create([
                    'customer_id' => $trans->customer_id,
                    'sales_bill_id' => $salesBill->id,
                    'type' => 'Reversal',
                    'points' => abs((float) $trans->points),
                    'amount_value' => $trans->amount_value,
                    'remarks' => "Refund of points redeemed on cancelled bill #{$salesBill->bill_number}",
                    'created_by' => auth()->id(),
                ]);
            }
        }
    }

    public function manualAdjustment(int $customerId, string $direction, float $points, string $remarks, ?int $userId = null): CustomerLoyaltyPoint
    {
        $type = ($direction === 'Deduct') ? 'Adjustment_Deduct' : 'Adjustment_Add';
        $program = $this->getActiveProgram();
        $rate = $program ? (float) $program->amount_per_point : 1.0;

        return CustomerLoyaltyPoint::create([
            'customer_id' => $customerId,
            'sales_bill_id' => null,
            'type' => $type,
            'points' => abs($points),
            'amount_value' => round(abs($points) * $rate, 2),
            'remarks' => $remarks,
            'created_by' => $userId ?: auth()->id(),
        ]);
    }
}
