<?php

namespace App\Services\Tax;

use App\Models\Item;

/**
 * The single place GST is calculated. Replaces the five near-identical computeLines()
 * implementations that used to live in Purchase/Sales/SalesReturn/Damage/OpeningStock
 * controllers and, critically, trusted whatever gst_percent the browser posted — this
 * always re-derives the rate from Item::gstTax server-side instead.
 */
class TaxEngine
{
    /**
     * @param  float  $discAmount  Explicit discount amount; discPercent is only applied
     *                             when this is 0.
     * @param  float  $extraDeductions  Additional taxable-value deductions applied after
     *                                  the discount (e.g. Opening Stock's scheme discounts).
     * @param  bool  $isInterstate  Local supplies split the tax 50/50 into CGST+SGST;
     *                              interstate supplies put the whole amount into IGST.
     *                              Only meaningful for documents that carry a Local/
     *                              Interstate type (Purchase Invoice, Sales Bill, Sales
     *                              Return) — left false (all-Local shape) elsewhere.
     * @param  bool|null  $isTaxInclusive  Explicit override for tax inclusive/exclusive calculation.
     *                                     When null, defaults to $item->tax_inclusive.
     * @return array{disc_amount: float, gst_percent: float, taxable_value: float, gst_tax_amount: float, cgst_amount: float, sgst_amount: float, igst_amount: float, net_amount: float}
     */
    public function calculate(
        float $qty,
        float $price,
        Item $item,
        float $discPercent = 0.0,
        float $discAmount = 0.0,
        float $extraDeductions = 0.0,
        bool $isInterstate = false,
        ?bool $isTaxInclusive = null,
        ?float $overrideGstPercent = null,
    ): array {
        $base = $qty * $price;

        if ($discAmount <= 0 && $discPercent > 0) {
            $discAmount = round($base * $discPercent / 100, 2);
        }

        $taxableValue = max(0, $base - $discAmount - $extraDeductions);
        $gstPercent = $overrideGstPercent !== null ? $overrideGstPercent : (float) ($item->gstTax->percentage ?? 0);

        $inclusive = $isTaxInclusive !== null ? $isTaxInclusive : (bool) $item->tax_inclusive;

        if ($inclusive && $gstPercent > 0) {
            $preTaxValue = round($taxableValue / (1 + $gstPercent / 100), 2);
            $gstTaxAmount = round($taxableValue - $preTaxValue, 2);
            $taxableValue = $preTaxValue;
        } else {
            $gstTaxAmount = round($taxableValue * $gstPercent / 100, 2);
        }

        if ($isInterstate) {
            $cgstAmount = 0.0;
            $sgstAmount = 0.0;
            $igstAmount = $gstTaxAmount;
        } else {
            $cgstAmount = round($gstTaxAmount / 2, 2);
            $sgstAmount = round($gstTaxAmount - $cgstAmount, 2); // remainder, so cgst+sgst always == gst_tax_amount
            $igstAmount = 0.0;
        }

        return [
            'disc_amount' => $discAmount,
            'gst_percent' => $gstPercent,
            'taxable_value' => $taxableValue,
            'gst_tax_amount' => $gstTaxAmount,
            'cgst_amount' => $cgstAmount,
            'sgst_amount' => $sgstAmount,
            'igst_amount' => $igstAmount,
            'net_amount' => round($taxableValue + $gstTaxAmount, 2),
        ];
    }
}
