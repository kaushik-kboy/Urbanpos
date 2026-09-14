<?php

namespace App\Services\Accounting;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Validation\ValidationException;

/**
 * Checks a party's LIVE outstanding balance (from their auto-created Ledger row) against
 * their credit_limit before a new credit sale/purchase. Customer.credit_balance and
 * Supplier.credit_balance are plain form fields — never updated by any sale/purchase — so
 * they cannot be used as the source of truth; Ledger::balance() (computed from posted
 * journal lines) is the only place the real running balance actually lives.
 */
class CreditLimitGuard
{
    public function assertWithinLimit(Customer|Supplier $party, float $newAmount): void
    {
        $creditLimit = (float) $party->credit_limit;

        if ($creditLimit <= 0) {
            return;
        }

        $balance = (float) ($party->ledger?->balance() ?? 0);

        // Sundry Debtors (Customer) is Dr-positive when they owe us; Sundry Creditors
        // (Supplier) is Cr-positive (i.e. balance() is negative) when we owe them.
        $outstanding = $party instanceof Customer ? max(0, $balance) : max(0, -$balance);

        if (($outstanding + $newAmount) > $creditLimit) {
            $label = $party instanceof Customer ? 'customer' : 'supplier';

            throw ValidationException::withMessages([
                'credit_limit' => "This transaction would push the {$label}'s outstanding balance to "
                    .number_format($outstanding + $newAmount, 2)." , exceeding their credit limit of "
                    .number_format($creditLimit, 2).'.',
            ]);
        }
    }
}
