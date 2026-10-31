<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Models\SalesBill;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DailyBillsExportController extends Controller
{
    /**
     * Export daily sales bills for internal synchronization (e.g. EOD Closing).
     */
    public function dailyBills(Request $request): JsonResponse
    {
        // 1. Internal Security Token & IP Check
        $expectedToken = config('services.followup_sync.token');
        $bearerToken   = $request->bearerToken() ?? $request->header('X-Internal-Token');

        if (blank($expectedToken) || ! hash_equals((string) $expectedToken, (string) $bearerToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing internal sync token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $clientIp = $request->ip();
        $allowedIps = ['127.0.0.1', '::1'];
        if (! in_array($clientIp, $allowedIps, true) && ! in_array('*', $allowedIps, true)) {
            return response()->json([
                'success' => false,
                'message' => "Access denied from IP address [{$clientIp}].",
            ], Response::HTTP_FORBIDDEN);
        }

        // 2. Validate Parameters
        $dateParam = $request->query('date', now()->toDateString());
        try {
            $parsedDate = Carbon::parse($dateParam)->format('Y-m-d');
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format. Expected YYYY-MM-DD.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $branchParam = trim((string) $request->query('branch', ''));

        // 3. Query Sales Bills for the day
        $query = SalesBill::with(['customer', 'payments.tenderType', 'branch'])
            ->whereDate('bill_date', $parsedDate)
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', '!=', 'Cancelled');
            });

        if ($branchParam !== '') {
            $query->where(function ($q) use ($branchParam) {
                $q->where('branch_id', $branchParam)
                  ->orWhereHas('branch', function ($bq) use ($branchParam) {
                      $bq->where('name', 'like', "%{$branchParam}%")
                         ->orWhere('erp_code', $branchParam);
                  });
            });
        }

        $bills = $query->orderBy('id', 'asc')->get();

        // 4. Transform Bills to EOD Expected Shape
        $results = $bills->map(function (SalesBill $bill) {
            $custName  = trim(($bill->customer?->title ? $bill->customer->title . ' ' : '') . ($bill->customer?->name ?? 'Walk-in Customer'));
            $rawPhone  = $bill->customer?->mobile ?: $bill->customer?->phone;
            $cleanPhone = $this->sanitizePhone($rawPhone);
            $mode      = $this->resolvePaymentMode($bill);

            return [
                'bill_number'   => (string) $bill->bill_number,
                'bill_date'     => $bill->bill_date ? Carbon::parse($bill->bill_date)->format('Y-m-d') : now()->toDateString(),
                'customer_name' => $custName ?: 'Walk-in Customer',
                'mobile'        => $cleanPhone,
                'payment_mode'  => $mode,
                'amount'        => (float) $bill->total,
                'branch_id'     => $bill->branch_id,
                'branch_name'   => $bill->branch?->name,
            ];
        });

        return response()->json([
            'success'     => true,
            'date'        => $parsedDate,
            'count'       => $results->count(),
            'bills'       => $results,
        ]);
    }

    /**
     * Map POS payment tenders to EOD 2-letter codes:
     * CA = Cash, CC = Card, CR = Credit, WA = Wallet/UPI, SP = Split
     */
    private function resolvePaymentMode(SalesBill $bill): string
    {
        $payments = $bill->payments->filter(fn ($p) => (float) $p->amount > 0);

        if ($payments->count() > 1) {
            // Check if they are distinct tender types
            $distinctTypes = $payments->pluck('tender_type_id')->unique();
            if ($distinctTypes->count() > 1) {
                return 'SP';
            }
        }

        if ($payments->isNotEmpty()) {
            $firstTender = $payments->first()->tenderType;
            if ($firstTender) {
                $tenderName = strtolower(trim($firstTender->name . ' ' . $firstTender->type));
                return $this->codeFromTenderName($tenderName);
            }
        }

        // Fallback to bill header payment_type
        $headerType = strtolower(trim((string) $bill->payment_type));
        return $this->codeFromTenderName($headerType);
    }

    private function codeFromTenderName(string $name): string
    {
        if (str_contains($name, 'cash')) {
            return 'CA';
        }
        if (str_contains($name, 'card') || str_contains($name, 'visa') || str_contains($name, 'master') || str_contains($name, 'pos')) {
            return 'CC';
        }
        if (str_contains($name, 'upi') || str_contains($name, 'qr') || str_contains($name, 'wallet') || str_contains($name, 'paytm') || str_contains($name, 'gpay') || str_contains($name, 'phonepe')) {
            return 'WA';
        }
        if (str_contains($name, 'credit') || str_contains($name, 'khata') || str_contains($name, 'udhaar')) {
            return 'CR';
        }

        return 'CA'; // default cash if unspecified
    }

    private function sanitizePhone(mixed $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }
}

