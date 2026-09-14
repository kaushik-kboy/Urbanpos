<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Explicit, named audit points only — matching this codebase's established pattern
 * (StockLedgerService/LedgerPostingService) of calling out exactly what's tracked rather
 * than a generic model observer logging every change on every model. Called from the
 * spec-named sensitive actions: invoice/bill/return cancellation, stock-update approval/
 * rejection, transfer cancellation, GST rate change, item price change, voucher deletion.
 */
class AuditLogger
{
    public function log(
        string $action,
        Model $subject,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
    ): AuditLog {
        $user = Auth::user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'branch_id' => $user?->branch_id,
            'action' => $action,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => Request::ip(),
        ]);
    }
}
