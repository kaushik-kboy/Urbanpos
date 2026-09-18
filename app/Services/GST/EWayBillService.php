<?php

namespace App\Services\GST;

use App\Models\SalesBill;
use Illuminate\Support\Collection;

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
}
