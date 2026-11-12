<?php

namespace App\Services\GST;

use App\Models\GstSetting;
use App\Models\SalesBill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EWayBillService
{
    /**
     * Official Indian GST State Code Dictionary.
     */
    public const STATE_CODES = [
        '01' => 'Jammu and Kashmir',
        '02' => 'Himachal Pradesh',
        '03' => 'Punjab',
        '04' => 'Chandigarh',
        '05' => 'Uttarakhand',
        '06' => 'Haryana',
        '07' => 'Delhi',
        '08' => 'Rajasthan',
        '09' => 'Uttar Pradesh',
        '10' => 'Bihar',
        '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland',
        '14' => 'Manipur',
        '15' => 'Mizoram',
        '16' => 'Tripura',
        '17' => 'Meghalaya',
        '18' => 'Assam',
        '19' => 'West Bengal',
        '20' => 'Jharkhand',
        '21' => 'Odisha',
        '22' => 'Chhattisgarh',
        '23' => 'Madhya Pradesh',
        '24' => 'Gujarat',
        '25' => 'Daman and Diu',
        '26' => 'Dadra and Nagar Haveli',
        '27' => 'Maharashtra',
        '28' => 'Andhra Pradesh (Old)',
        '29' => 'Karnataka',
        '30' => 'Goa',
        '31' => 'Lakshadweep',
        '32' => 'Kerala',
        '33' => 'Tamil Nadu',
        '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands',
        '36' => 'Telangana',
        '37' => 'Andhra Pradesh (New)',
        '38' => 'Ladakh',
        '97' => 'Other Territory',
    ];

    /**
     * Resolve 2-digit GST state code integer from GSTIN or state name.
     */
    public function resolveStateCode(?string $gstin, ?string $stateName): int
    {
        // 1. From GSTIN (first 2 digits)
        if ($gstin && strlen(trim($gstin)) >= 2) {
            $prefix = substr(trim($gstin), 0, 2);
            if (is_numeric($prefix) && isset(self::STATE_CODES[$prefix])) {
                return (int) $prefix;
            }
        }

        // 2. From State Name match
        if ($stateName) {
            $cleaned = strtolower(trim($stateName));
            foreach (self::STATE_CODES as $code => $name) {
                if (strtolower($name) === $cleaned || str_contains(strtolower($name), $cleaned) || str_contains($cleaned, strtolower($name))) {
                    return (int) $code;
                }
            }
        }

        // Default to Delhi (07) or Maharashtra (27) fallback if unknown
        return 7;
    }

    /**
     * Map internal UOM names to standard Government 3-letter Unit Codes.
     */
    public function normalizeUom(?string $uomName): string
    {
        if (empty($uomName)) {
            return 'NOS';
        }

        $clean = strtoupper(trim($uomName));

        $map = [
            'PCS' => 'NOS',
            'PIECES' => 'NOS',
            'PIECE' => 'NOS',
            'NUMBERS' => 'NOS',
            'NO' => 'NOS',
            'NOS' => 'NOS',
            'KG' => 'KGS',
            'KGS' => 'KGS',
            'KILOGRAM' => 'KGS',
            'GM' => 'GMS',
            'GRAM' => 'GMS',
            'LTR' => 'LTR',
            'LITER' => 'LTR',
            'LITRE' => 'LTR',
            'ML' => 'MLT',
            'BOX' => 'BOX',
            'BOTTLE' => 'BTL',
            'CAN' => 'CAN',
            'PACK' => 'PAC',
            'PACKET' => 'PAC',
            'BAG' => 'BAG',
            'METRE' => 'MTR',
            'METER' => 'MTR',
        ];

        return $map[$clean] ?? (strlen($clean) <= 3 ? $clean : 'NOS');
    }

    /**
     * Generate NIC single bill dictionary.
     */
    public function buildBillEntry(SalesBill $bill): array
    {
        $bill->loadMissing(['customer', 'branch', 'items.item.gstTax']);

        $branch = $bill->branch;
        $customer = $bill->customer;

        $fromGstin = trim($branch?->gst_no ?? 'URP');
        $fromStateCode = $this->resolveStateCode($fromGstin, $branch?->state);

        $isB2B = !empty($customer?->gst_no);
        $toGstin = $isB2B ? strtoupper(trim($customer->gst_no)) : 'URP';
        $toStateCode = $this->resolveStateCode($isB2B ? $toGstin : null, $customer?->state ?? $branch?->state);

        // Format dates according to NIC (DD/MM/YYYY)
        $docDate = $bill->bill_date ? $bill->bill_date->format('d/m/Y') : now()->format('d/m/Y');
        $transDocDate = $bill->transport_doc_date ? $bill->transport_doc_date->format('d/m/Y') : '';

        // Item List
        $itemList = [];
        $totalTaxable = 0.0;
        $itemCounter = 1;

        foreach ($bill->items as $item) {
            $product = $item->item;
            $hsn = (int) ($product?->hsn_code ?: 2309); // Default pet food / general HSN
            if ($hsn <= 0) {
                $hsn = 9999;
            }

            $taxable = round((float) $item->net_amount - (float) $item->gst_tax_amount, 2);
            $totalTaxable += $taxable;

            $gstRate = (float) $item->gst_percent;
            $isInterstate = ($fromStateCode !== $toStateCode) || ((float) $item->igst_amount > 0);

            $itemList[] = [
                'itemNo' => $itemCounter++,
                'productName' => mb_substr($product?->name ?? 'Product Item', 0, 100),
                'productDesc' => mb_substr($product?->alias ?? $product?->name ?? 'Goods', 0, 100),
                'hsnCode' => $hsn,
                'quantity' => (float) $item->qty,
                'qtyUnit' => $this->normalizeUom($product?->uom?->name ?? 'NOS'),
                'taxableAmount' => $taxable,
                'sgstRate' => $isInterstate ? 0 : round($gstRate / 2, 2),
                'cgstRate' => $isInterstate ? 0 : round($gstRate / 2, 2),
                'igstRate' => $isInterstate ? round($gstRate, 2) : 0,
                'cessRate' => 0,
            ];
        }

        // NIC Schema requires at least 1 item in itemList
        if (empty($itemList)) {
            $taxable = max(0, round((float) $bill->total - (float) $bill->total_gst, 2));
            $isInterstate = ($fromStateCode !== $toStateCode) || ((float) $bill->total_igst > 0);
            $gstRate = $taxable > 0 ? round(((float) $bill->total_gst / $taxable) * 100, 2) : 18;

            $itemList[] = [
                'itemNo' => 1,
                'productName' => 'Retail Consignment Goods',
                'productDesc' => 'Pet Care & Accessories',
                'hsnCode' => 2309,
                'quantity' => (float) ($bill->total_qty ?: 1),
                'qtyUnit' => 'NOS',
                'taxableAmount' => $taxable,
                'sgstRate' => $isInterstate ? 0 : round($gstRate / 2, 2),
                'cgstRate' => $isInterstate ? 0 : round($gstRate / 2, 2),
                'igstRate' => $isInterstate ? round($gstRate, 2) : 0,
                'cessRate' => 0,
            ];
            $totalTaxable = $taxable;
        }

        // Approx distance default to 20 or transport_distance
        $distance = $bill->transport_distance ? (int) $bill->transport_distance : 20;

        return [
            'userGstin' => $fromGstin,
            'supplyType' => 'O', // Outward
            'subSupplyType' => '1', // Supply
            'subSupplyDesc' => '',
            'docType' => 'INV', // Tax Invoice
            'docNo' => preg_replace('/[^A-Za-z0-9\-\/]/', '', $bill->bill_number),
            'docDate' => $docDate,
            'transType' => 1, // Regular

            // Dispatch / Seller Details
            'fromGstin' => $fromGstin,
            'fromTrdName' => mb_substr($branch?->name ?? 'UrbanPOS Enterprise', 0, 100),
            'fromAddr1' => mb_substr($branch?->address_line1 ?? 'Main Market Road', 0, 120),
            'fromAddr2' => mb_substr($branch?->address_line2 ?? '', 0, 120),
            'fromPlace' => mb_substr($branch?->city ?? 'City', 0, 50),
            'fromPincode' => (int) preg_replace('/\D/', '', $branch?->postal_code ?? '110001'),
            'actFromStateCode' => $fromStateCode,
            'fromStateCode' => $fromStateCode,

            // Buyer / Consignee Details
            'toGstin' => $toGstin,
            'toTrdName' => mb_substr($customer?->name ?? 'Walk-in Customer', 0, 100),
            'toAddr1' => mb_substr($customer?->address1 ?? $branch?->address_line1 ?? 'Counter Delivery', 0, 120),
            'toAddr2' => '',
            'toPlace' => mb_substr($customer?->city ?? $branch?->city ?? 'City', 0, 50),
            'toPincode' => (int) preg_replace('/\D/', '', $customer?->postal_code ?? $branch?->postal_code ?? '110001'),
            'actToStateCode' => $toStateCode,
            'toStateCode' => $toStateCode,

            'transactionType' => 1,

            // Financial Summary
            'totalValue' => round($totalTaxable, 2),
            'cgstValue' => round((float) $bill->total_cgst, 2),
            'sgstValue' => round((float) $bill->total_sgst, 2),
            'igstValue' => round((float) $bill->total_igst, 2),
            'cessValue' => round((float) ($bill->total_extra_cess ?? 0), 2),
            'totInvValue' => round((float) $bill->total, 2),

            // Part-B Transport Details
            'transporterId' => strtoupper(trim($bill->transporter_id ?? '')),
            'transporterName' => mb_substr(trim($bill->transporter_name ?? ''), 0, 100),
            'transDocNo' => trim($bill->transport_doc_no ?? ''),
            'transMode' => (string) ($bill->transport_mode ?: '1'),
            'transDistance' => (string) $distance,
            'transDocDate' => $transDocDate,
            'vehicleNo' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $bill->vehicle_no ?? '')),
            'vehicleType' => $bill->vehicle_type === 'O' ? 'O' : 'R',

            'itemList' => $itemList,
        ];
    }

    /**
     * Generate NIC bulk/single JSON document structure.
     */
    public function generatePayload(Collection|SalesBill $bills): array
    {
        $billCollection = $bills instanceof SalesBill ? collect([$bills]) : $bills;

        $billLists = [];
        foreach ($billCollection as $bill) {
            $billLists[] = $this->buildBillEntry($bill);
        }

        return [
            'version' => '1.0.0621',
            'billLists' => $billLists,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GSP API METHODS — Auto Push, Cancel, Part-B Update, Extend Validity
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Auto-generate E-Way Bill via GSP API (or mock in sandbox mode).
     * Called automatically on bill save when total >= ₹50,000.
     */
    public function generateEwb(SalesBill $bill): array
    {
        try {
            if ($bill->hasEwayBill()) {
                return ['success' => false, 'error' => 'E-Way Bill already generated for this bill.'];
            }

            $settings = GstSetting::current();
            $payload = $this->buildBillEntry($bill);

            // Mock Mode — skip real GSP call when provider is mock or credentials are missing
            if ($settings->gsp_provider === 'mock' || empty($settings->client_id) || empty($settings->client_secret)) {
                $fakeEwbNo = '34' . str_pad(random_int(1000000000, 9999999999), 10, '0');
                $validUntil = now()->addDays(
                    in_array($bill->transport_mode, ['2', '3', '4']) ? 15 : 1
                );
                $bill->update([
                    'eway_bill_no'    => $fakeEwbNo,
                    'eway_bill_date'  => now(),
                    'eway_valid_until' => $validUntil,
                    'eway_status'     => 'Generated',
                ]);
                Log::info("Mock EWB generated for Bill #{$bill->bill_number}: {$fakeEwbNo}");
                return ['success' => true, 'ewb_no' => $fakeEwbNo, 'valid_until' => $validUntil, 'mode' => 'mock'];
            }

            // Live GSP Call
            $result = $this->callEwbGspApi($settings, 'ewbgenerate', $payload);
            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'GSP EWB generation failed.');
            }

            $bill->update([
                'eway_bill_no'    => $result['ewb_no'],
                'eway_bill_date'  => $result['ewb_date'] ?? now(),
                'eway_valid_until' => $result['valid_until'] ?? now()->addDays(1),
                'eway_status'     => 'Generated',
            ]);

            Log::info("Live EWB generated for Bill #{$bill->bill_number}: {$result['ewb_no']}");
            return $result;
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            Log::error("EWB generation failed for Bill #{$bill->bill_number}: {$msg}");
            return ['success' => false, 'error' => $msg];
        }
    }

    /**
     * Cancel an E-Way Bill via GSP.
     * Reason codes: 1=Duplicate, 2=Order changed, 3=Data entry mistake, 4=Others
     */
    public function cancelEwb(SalesBill $bill, string $reasonCode = '2', string $remarks = 'Cancelled'): array
    {
        try {
            if (!$bill->hasEwayBill()) {
                throw new \Exception('No E-Way Bill found for this invoice.');
            }

            $settings = GstSetting::current();

            if ($settings->gsp_provider !== 'mock' && !empty($settings->client_id) && !empty($settings->client_secret)) {
                $result = $this->callEwbGspApi($settings, 'ewaycancel', [
                    'ewbNo'     => $bill->eway_bill_no,
                    'cancelRsnCode' => (int) $reasonCode,
                    'cancelRmrk'   => mb_substr($remarks, 0, 100),
                ]);
                if (!$result['success']) {
                    throw new \Exception($result['error'] ?? 'GSP EWB cancellation failed.');
                }
            }

            $reasonNames = ['1' => 'Duplicate', '2' => 'Order changed', '3' => 'Data entry mistake', '4' => 'Others'];
            $bill->update([
                'eway_status'    => 'Cancelled',
                'eway_bill_no'   => null,
                'eway_valid_until' => null,
            ]);

            Log::info("EWB cancelled for Bill #{$bill->bill_number} — Reason: " . ($reasonNames[$reasonCode] ?? 'Unknown'));
            return ['success' => true, 'message' => 'E-Way Bill cancelled successfully.'];
        } catch (\Throwable $e) {
            Log::error("EWB cancel failed for Bill #{$bill->bill_number}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update Part-B (Vehicle Number / Transporter) of an existing EWB via GSP.
     * Allowed multiple times before validity expires.
     */
    public function updatePartB(SalesBill $bill, string $vehicleNo, string $transMode = '1'): array
    {
        try {
            if (!$bill->hasEwayBill()) {
                throw new \Exception('No active E-Way Bill to update Part-B.');
            }

            $settings = GstSetting::current();
            $cleanVehicle = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $vehicleNo));

            if ($settings->gsp_provider !== 'mock' && !empty($settings->client_id) && !empty($settings->client_secret)) {
                $result = $this->callEwbGspApi($settings, 'vehewb', [
                    'ewbNo'     => $bill->eway_bill_no,
                    'vehicleNo' => $cleanVehicle,
                    'fromPlace' => $bill->branch?->city ?? 'City',
                    'fromState' => $this->resolveStateCode($bill->branch?->gst_no, $bill->branch?->state),
                    'transDocNo'   => $bill->transport_doc_no ?? '',
                    'transDocDate' => $bill->transport_doc_date?->format('d/m/Y') ?? '',
                    'transMode' => $transMode,
                    'vehicleType' => $bill->vehicle_type ?? 'R',
                ]);
                if (!$result['success']) {
                    throw new \Exception($result['error'] ?? 'Part-B update failed.');
                }
            }

            $bill->update([
                'vehicle_no'     => $cleanVehicle,
                'transport_mode' => $transMode,
            ]);

            Log::info("EWB Part-B updated for Bill #{$bill->bill_number}: Vehicle {$cleanVehicle}");
            return ['success' => true, 'message' => "Vehicle updated to {$cleanVehicle} successfully."];
        } catch (\Throwable $e) {
            Log::error("EWB Part-B update failed for Bill #{$bill->bill_number}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Extend E-Way Bill validity (when goods not delivered within validity).
     * UrbanPos allows extending from the current location with a new vehicle.
     */
    public function extendValidity(SalesBill $bill, string $vehicleNo, string $fromPlace, int $remainingDistance = 20): array
    {
        try {
            if (!$bill->hasEwayBill()) {
                throw new \Exception('No active E-Way Bill to extend.');
            }

            $settings = GstSetting::current();
            $cleanVehicle = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $vehicleNo));
            $fromStateCode = $this->resolveStateCode($bill->branch?->gst_no, $bill->branch?->state);

            if ($settings->gsp_provider !== 'mock' && !empty($settings->client_id) && !empty($settings->client_secret)) {
                $result = $this->callEwbGspApi($settings, 'extendvalidity', [
                    'ewbNo'          => $bill->eway_bill_no,
                    'vehicleNo'      => $cleanVehicle,
                    'fromPlace'      => mb_substr($fromPlace, 0, 50),
                    'fromState'      => $fromStateCode,
                    'remainingDistance' => $remainingDistance,
                    'transDocNo'     => $bill->transport_doc_no ?? '',
                    'transDocDate'   => $bill->transport_doc_date?->format('d/m/Y') ?? '',
                    'transMode'      => $bill->transport_mode ?? '1',
                    'vehicleType'    => $bill->vehicle_type ?? 'R',
                    'extnRsnCode'    => 5, // Others
                    'extnRemarks'    => 'Extended due to transit delay',
                    'consignmentStatus' => 'M', // In Movement
                    'transitType'    => 'R', // Road
                ]);
                if (!$result['success']) {
                    throw new \Exception($result['error'] ?? 'EWB validity extension failed.');
                }
                $newValidity = $result['valid_until'] ?? now()->addDays(1);
            } else {
                // Mock: just add 1 day
                $newValidity = ($bill->eway_valid_until ?? now())->addDays(1);
            }

            $bill->update([
                'vehicle_no'       => $cleanVehicle,
                'eway_valid_until'  => $newValidity,
            ]);

            Log::info("EWB extended for Bill #{$bill->bill_number} till {$newValidity}, Vehicle: {$cleanVehicle}");
            return ['success' => true, 'message' => "E-Way Bill extended. New validity: " . \Carbon\Carbon::parse($newValidity)->format('d-m-Y h:i A')];
        } catch (\Throwable $e) {
            Log::error("EWB extend failed for Bill #{$bill->bill_number}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get or cache Sandbox.co.in Government E-Way Bill Taxpayer Session Token.
     */
    public function getSandboxEwbSessionToken(GstSetting $settings): ?string
    {
        $cacheKey = 'sandbox_ewb_session_' . md5($settings->gstin . $settings->username . ($settings->is_sandbox ? '1' : '0'));
        return Cache::remember($cacheKey, 18000, function () use ($settings) {
            $host = $settings->is_sandbox ? 'https://test-api.sandbox.co.in' : 'https://api.sandbox.co.in';

            $baseCacheKey = 'sandbox_token_' . md5($settings->client_id . $settings->client_secret . ($settings->is_sandbox ? '1' : '0'));
            $baseToken = Cache::remember($baseCacheKey, 82800, function () use ($settings, $host) {
                try {
                    $authRes = Http::withHeaders([
                        'x-api-key' => $settings->client_id,
                        'x-api-secret' => $settings->client_secret,
                        'x-api-version' => '1.0',
                    ])->timeout(10)->post($host . '/authenticate');

                    if ($authRes->successful()) {
                        return $authRes->json('data.access_token') ?? $authRes->json('access_token');
                    }
                    Log::error('Sandbox.co.in EWB base auth error: ' . $authRes->body());
                    return null;
                } catch (\Throwable $e) {
                    Log::error('Sandbox.co.in EWB base auth exception: ' . $e->getMessage());
                    return null;
                }
            });

            if (!$baseToken) {
                return null;
            }

            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'authorization' => $baseToken,
                    'x-api-key' => $settings->client_id,
                    'x-api-version' => '1.0.0',
                    'x-source' => 'primary',
                ])->timeout(15)->post($host . '/gst/compliance/e-way-bill/tax-payer/authenticate?force=true', [
                    'username' => $settings->username,
                    'password' => $settings->password,
                    'gstin' => $settings->gstin,
                ]);

                if ($response->successful()) {
                    $body = $response->json();
                    if (($body['data']['Status'] ?? 0) === 1 && !empty($body['data']['access_token'])) {
                        return $body['data']['access_token'];
                    }
                    $errMsg = $body['data']['ErrorDetails'][0]['ErrorMessage'] ?? $response->body();
                    Log::error("Sandbox EWB taxpayer session error: {$errMsg}");
                    return null;
                }

                Log::error('Sandbox EWB taxpayer session HTTP error: ' . $response->body());
                return null;
            } catch (\Throwable $e) {
                Log::error('Sandbox EWB taxpayer session exception: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Parse Indian NIC/Sandbox EWB date strings (e.g. "12/11/2026 02:30:00 PM") safely.
     */
    protected function parseEwbDate(?string $dateStr): \Carbon\Carbon
    {
        if (empty($dateStr)) {
            return now();
        }
        foreach (['d/m/Y h:i:s A', 'd/m/Y H:i:s', 'd/m/Y', 'Y-m-d H:i:s'] as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, trim($dateStr));
            } catch (\Throwable) {
                // try next format
            }
        }
        try {
            return \Carbon\Carbon::parse($dateStr);
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * Shared HTTP helper for all EWB GSP API calls.
     * Endpoint action: 'ewbgenerate' | 'ewaycancel' | 'vehewb' | 'extendvalidity'
     */
    protected function callEwbGspApi(GstSetting $settings, string $action, array $data): array
    {
        if ($settings->gsp_provider === 'sandbox') {
            return $this->callSandboxEwbApi($settings, $action, $data);
        }

        $baseUrl = $settings->is_sandbox
            ? 'https://einvoice1-uat.nic.in/EWB/apicall/'
            : 'https://ewaybillgst.gov.in/apicall/';

        if ($settings->gsp_provider === 'cleartax') {
            $baseUrl = $settings->is_sandbox
                ? 'https://api-sandbox.cleartax.in/gsp/eway/'
                : 'https://api.cleartax.in/gsp/eway/';
        } elseif ($settings->gsp_provider === 'masters_india') {
            $baseUrl = $settings->is_sandbox
                ? 'https://sandbox.mastersindia.net/gsp/eway/'
                : 'https://api.mastersindia.net/gsp/eway/';
        }

        try {
            $headers = [
                'Content-Type' => 'application/json',
                'client_id' => $settings->client_id,
                'client_secret' => $settings->client_secret,
                'gstin' => $settings->gstin,
                'username' => $settings->username,
            ];

            $response = Http::withHeaders($headers)->timeout(15)->post($baseUrl . $action, $data);

            if ($response->successful()) {
                $body = $response->json();
                return [
                    'success'     => true,
                    'ewb_no'      => (string) ($body['ewbNo'] ?? $body['EwbNo'] ?? ''),
                    'ewb_date'    => isset($body['ewbDt']) ? $this->parseEwbDate($body['ewbDt']) : now(),
                    'valid_until' => isset($body['validUpto']) ? $this->parseEwbDate($body['validUpto']) : now()->addDays(1),
                    'raw'         => $body,
                ];
            }

            return [
                'success' => false,
                'error'   => $response->json('message') ?? $response->json('error') ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'GSP Timeout: ' . $e->getMessage()];
        }
    }

    /**
     * Execute Sandbox.co.in E-Way Bill API request.
     */
    protected function callSandboxEwbApi(GstSetting $settings, string $action, array $data): array
    {
        $sessionToken = $this->getSandboxEwbSessionToken($settings);
        if (!$sessionToken) {
            return [
                'success' => false,
                'error' => 'Could not establish Government E-Way Bill session with Sandbox. Check credentials.',
            ];
        }

        $host = $settings->is_sandbox ? 'https://test-api.sandbox.co.in' : 'https://api.sandbox.co.in';
        $ewbNo = $data['ewbNo'] ?? '';

        [$method, $endpoint] = match ($action) {
            'ewbgenerate'    => ['POST', "{$host}/gst/compliance/e-way-bill/consignor/bill"],
            'ewaycancel'     => ['POST', "{$host}/gst/compliance/e-way-bill/consignor/bill/{$ewbNo}/cancel"],
            'vehewb'         => ['PUT',  "{$host}/gst/compliance/e-way-bill/consignor/bill/{$ewbNo}/vehicle"],
            'extendvalidity' => ['POST', "{$host}/gst/compliance/e-way-bill/consignor/bill/{$ewbNo}/extend"],
            default          => ['POST', "{$host}/gst/compliance/e-way-bill/consignor/bill"],
        };

        try {
            $headers = [
                'Content-Type' => 'application/json',
                'authorization' => $sessionToken,
                'x-api-key' => $settings->client_id,
                'x-api-version' => '1.0.0',
                'x-source' => 'primary',
            ];

            $http = Http::withHeaders($headers)->timeout(20);
            $response = $method === 'PUT'
                ? $http->put($endpoint, $data)
                : $http->post($endpoint, $data);

            $body = $response->json();
            if ($response->successful() && ($body['code'] ?? 200) === 200) {
                $statusVal = (string) ($body['data']['status'] ?? $body['data']['Status'] ?? '1');
                if ($statusVal === '0') {
                    $err = $body['data']['info']
                        ?? ($body['data']['ErrorDetails'][0]['ErrorMessage'] ?? null)
                        ?? ($body['data']['error']['errorCodes'] ?? null)
                        ?? 'Government EWB portal rejected the request.';
                    return ['success' => false, 'error' => (string) $err];
                }

                $ewbData = $body['data']['data'] ?? $body['data'] ?? $body;
                return [
                    'success'     => true,
                    'ewb_no'      => (string) ($ewbData['ewayBillNo'] ?? $ewbData['ewbNo'] ?? $ewbData['EwbNo'] ?? $ewbNo),
                    'ewb_date'    => $this->parseEwbDate($ewbData['ewayBillDate'] ?? $ewbData['ewbDt'] ?? null),
                    'valid_until' => $this->parseEwbDate($ewbData['validUpto'] ?? null),
                    'raw'         => $body,
                ];
            }

            $err = $body['data']['info']
                ?? ($body['data']['ErrorDetails'][0]['ErrorMessage'] ?? null)
                ?? $body['message']
                ?? $response->body();

            return ['success' => false, 'error' => (string) $err];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Sandbox EWB Gateway Timeout: ' . $e->getMessage()];
        }
    }
}
