<?php

namespace App\Jobs;

use App\Models\SalesBill;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncBillToFollowUpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $salesBillId
    ) {
        $this->onConnection(config('services.followup_sync.connection', 'sync'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! config('services.followup_sync.enabled', true)) {
            return;
        }

        $url   = config('services.followup_sync.url');
        $token = config('services.followup_sync.token');

        if (blank($url) || blank($token)) {
            Log::warning("SyncBillToFollowUpJob: FOLLOWUP_API_URL or FOLLOWUP_SYNC_TOKEN is not configured.");
            return;
        }

        $bill = SalesBill::with(['customer', 'branch', 'payments.tenderType'])->find($this->salesBillId);

        if (! $bill) {
            Log::warning("SyncBillToFollowUpJob: SalesBill ID {$this->salesBillId} not found.");
            return;
        }

        if (! $bill->customer) {
            Log::info("SyncBillToFollowUpJob: Bill #{$bill->bill_number} has no linked customer, skipping follow-up sync.");
            return;
        }

        $rawPhone = $bill->customer->mobile ?: $bill->customer->phone;
        $phone    = $this->sanitizePhone($rawPhone);

        if (blank($phone)) {
            Log::info("SyncBillToFollowUpJob: Customer for bill #{$bill->bill_number} does not have a valid 10-digit phone, skipping.");
            return;
        }

        $customerName = trim((string) ($bill->customer->name ?? 'Customer'));
        $billDate     = $bill->bill_date ? Carbon::parse($bill->bill_date)->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $addressParts = array_filter([
            $bill->customer->address1,
            $bill->customer->city,
            $bill->customer->state,
            $bill->customer->postal_code,
        ]);
        $fullAddress  = ! empty($addressParts) ? implode(', ', $addressParts) : null;

        // Normalize branch name strictly to Motera or Satellite for CRM parity
        $rawBranch = (string) ($bill->branch?->name ?? '');
        $normalizedBranch = null;
        if (stripos($rawBranch, 'motera') !== false) {
            $normalizedBranch = 'Motera';
        } elseif (stripos($rawBranch, 'satellite') !== false) {
            $normalizedBranch = 'Satellite';
        } elseif (filled($rawBranch)) {
            $normalizedBranch = trim(str_ireplace(['Urban Pets |', 'Urban Pets -', '|', '-'], '', $rawBranch));
        }

        // Delivery specifics
        $salesType    = trim((string) ($bill->sales_type ?? ''));
        $fulfillment  = trim((string) ($bill->fulfillment ?? $bill->delivery_type ?? ''));
        $deliveryType = trim((string) ($bill->delivery_type ?? 'Delivered'));

        // If Fulfillment is Delivery OR Other (or legacy Home / Home Delivery), it is Home Delivery
        $isHomeDelivery = in_array(strtolower($fulfillment), ['delivery', 'other', 'home delivery', 'home'], true)
            || in_array(strtolower($deliveryType), ['home delivery', 'home', 'delivery', 'other'], true)
            || in_array(strtolower($salesType), ['home', 'home delivery'], true);

        $effectiveDeliveryType = $isHomeDelivery ? ($fulfillment ?: 'Home Delivery') : ($fulfillment ?: $deliveryType);
        $deliveryDate = $bill->delivery_time 
            ? Carbon::parse($bill->delivery_time)->format('Y-m-d') 
            : ($bill->bill_date ? Carbon::parse($bill->bill_date)->format('Y-m-d') : now()->toDateString());
        $notes = trim((string) ($bill->remarks ?: $bill->message));

        // Payment status determination for rider collection
        $paymentStatus = 'pending';
        $paidAmount = (float) $bill->payments->sum('amount');
        $headerPayment = strtolower(trim((string) $bill->payment_type));

        if ($paidAmount >= (float) $bill->total && (float) $bill->total > 0) {
            $paymentStatus = str_contains($headerPayment, 'cash') ? 'cash' : 'online';
        } elseif ($headerPayment === 'cash') {
            $paymentStatus = 'cash';
        } elseif (in_array($headerPayment, ['card', 'upi', 'online', 'wallet'], true)) {
            $paymentStatus = 'online';
        } elseif ($headerPayment === 'credit' || (float) $bill->total <= 0) {
            $paymentStatus = 'pending';
        }

        $payload = [
            'bill_number'      => (string) $bill->bill_number,
            'bill_date'        => $billDate,
            'amount'           => (float) $bill->total,
            'customer_name'    => $customerName ?: 'Customer',
            'customer_phone'   => $phone,
            'customer_email'   => $bill->customer->email ?: null,
            'customer_address' => $fullAddress,
            'branch_name'      => $normalizedBranch ?: ($bill->branch?->name ?: null),
            'branch_code'      => $bill->branch?->erp_code ?: null,
            'sales_type'       => $salesType,
            'fulfillment'      => $fulfillment ?: $deliveryType,
            'delivery_type'    => $effectiveDeliveryType,
            'delivery_date'    => $deliveryDate,
            'payment_status'   => $paymentStatus,
            'notes'            => $notes ?: null,
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(8)
                ->post($url, $payload);

            if ($response->successful()) {
                Log::info("SyncBillToFollowUpJob: Successfully synced bill #{$bill->bill_number} to Follow-up CRM.");
            } else {
                Log::warning("SyncBillToFollowUpJob: Follow-up sync returned HTTP {$response->status()} for bill #{$bill->bill_number}: " . $response->body());

                if ($response->serverError()) {
                    throw new \RuntimeException("Server error from Follow-up CRM (HTTP {$response->status()})");
                }
            }
        } catch (\Throwable $e) {
            Log::error("SyncBillToFollowUpJob exception for bill #{$bill->bill_number}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Normalize Indian phone numbers to standard 10 digits.
     */
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

