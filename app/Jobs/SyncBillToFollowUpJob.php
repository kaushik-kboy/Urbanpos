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

        $bill = SalesBill::with([
            'customer.pets.petType',
            'customer.pets.breed',
            'customer.area',
            'customerAddress.area',
            'branch',
            'payments.tenderType',
        ])->find($this->salesBillId);

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

        // If Fulfillment is Delivery OR Other (or legacy Home / Home Delivery / Delivered), it is Home Delivery
        $isHomeDelivery = in_array(strtolower($fulfillment), ['delivery', 'delivered', 'other', 'home delivery', 'home'], true)
            || in_array(strtolower($deliveryType), ['home delivery', 'home', 'delivery', 'delivered', 'other'], true)
            || in_array(strtolower($salesType), ['home', 'home delivery', 'delivery'], true);

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

        $customer = $bill->customer;
        $custAddr = $bill->customer_address_id ? $bill->customerAddress : null;

        // Prioritize bill-selected delivery address, fallback to customer profile address
        if ($custAddr) {
            $streetAddress = $custAddr->address_line ?: $customer?->address1;
            $city          = $custAddr->city ?: $customer?->city;
            $state         = $custAddr->state ?: $customer?->state;
            $postalCode    = $custAddr->postal_code ?: $customer?->postal_code;
            $areaName      = $custAddr->area?->name ?: $custAddr->area_name ?: $customer?->area?->name;
            $fullAddress   = $custAddr->formatted_address ?: null;
            $googleMapLink = $customer?->google_map_link ?: null;
        } else {
            $streetAddress = $customer?->address1 ?: null;
            $city          = $customer?->city ?: null;
            $state         = $customer?->state ?: null;
            $postalCode    = $customer?->postal_code ?: null;
            $areaName      = $customer?->area?->name ?: null;
            $googleMapLink = $customer?->google_map_link ?: null;
        }

        if (empty($fullAddress)) {
            $addressParts = array_filter([
                $streetAddress,
                $city,
                $state,
                $postalCode,
            ]);
            $fullAddress = ! empty($addressParts) ? implode(', ', $addressParts) : null;
        }

        $gstNo         = $customer?->gst_no ?: null;
        $customerCode  = $customer?->customer_code ?: null;

        // Custom field: Instagram ID
        $instagramId = null;
        if ($customer) {
            try {
                $customFields = $customer->custom_fields_map ?? [];
                foreach ($customFields as $k => $v) {
                    if (stripos((string) $k, 'instagram') !== false && filled($v)) {
                        $instagramId = (string) $v;
                        break;
                    }
                }
            } catch (\Throwable) {
                $instagramId = null;
            }
        }

        // Pets
        $pets = [];
        if ($customer && $customer->pets) {
            foreach ($customer->pets as $pet) {
                if (blank($pet->name)) {
                    continue;
                }
                $pets[] = [
                    'name'       => (string) $pet->name,
                    'pet_type'   => $pet->petType?->name ?: null,
                    'breed'      => $pet->breed?->name ?: null,
                    'gender'     => $pet->gender ?: null,
                    'birth_date' => $pet->birth_date ? Carbon::parse($pet->birth_date)->format('Y-m-d') : null,
                ];
            }
        }

        $payload = [
            'bill_number'      => (string) $bill->bill_number,
            'bill_date'        => $billDate,
            'amount'           => (float) $bill->total,
            'customer_name'    => $customerName ?: 'Customer',
            'customer_phone'   => $phone,
            'customer_email'   => $customer?->email ?: null,
            'customer_address' => $fullAddress,
            'street_address'   => $streetAddress,
            'city'             => $city,
            'state'            => $state,
            'postal_code'      => $postalCode,
            'area'             => $areaName,
            'google_map_link'  => $googleMapLink,
            'gst_no'           => $gstNo,
            'customer_code'    => $customerCode,
            'instagram_id'     => $instagramId,
            'pets'             => $pets,
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

