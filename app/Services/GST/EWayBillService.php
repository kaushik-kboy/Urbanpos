<?php

namespace App\Services\GST;

use App\Models\GstSetting;
use App\Models\SalesBill;
use Illuminate\Support\Collection;
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

            // Mock / Sandbox Mode — skip real GSP call
            if ($settings->gsp_provider === 'mock' || $settings->is_sandbox) {
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

            if ($settings->gsp_provider !== 'mock' && !$settings->is_sandbox) {
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

            if ($settings->gsp_provider !== 'mock' && !$settings->is_sandbox) {
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
     * GoFrugal allows extending from the current location with a new vehicle.
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

            if ($settings->gsp_provider !== 'mock' && !$settings->is_sandbox) {
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
     * Shared HTTP helper for all EWB GSP API calls.
     * Endpoint action: 'ewbgenerate' | 'ewaycancel' | 'vehewb' | 'extendvalidity'
     */
    protected function callEwbGspApi(GstSetting $settings, string $action, array $data): array
    {
        $baseUrl = $settings->is_sandbox
            ? 'https://einvoice1-uat.nic.in/EWB/apicall/'
            : 'https://ewaybillgst.gov.in/apicall/';

        // Provider-specific base URL overrides
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
            $response = Http::withHeaders([
                'client_id'     => $settings->client_id,
                'client_secret' => $settings->client_secret,
                'gstin'         => $settings->gstin,
                'username'      => $settings->username,
                'Content-Type'  => 'application/json',
            ])->timeout(15)->post($baseUrl . $action, $data);

            if ($response->successful()) {
                $body = $response->json();
                return [
                    'success'     => true,
                    'ewb_no'      => (string) ($body['ewbNo'] ?? $body['EwbNo'] ?? ''),
                    'ewb_date'    => isset($body['ewbDt']) ? \Carbon\Carbon::parse($body['ewbDt']) : now(),
                    'valid_until' => isset($body['validUpto']) ? \Carbon\Carbon::parse($body['validUpto']) : now()->addDays(1),
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
}
