<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\DateHelper;

class NormalizeDateInputs
{
    /**
     * Common request keys that represent date values.
     */
    protected array $dateKeys = [
        'from',
        'to',
        'from_date',
        'to_date',
        'date_from',
        'date_to',
        'invoice_date',
        'grn_date',
        'supplier_inv_date',
        'order_date',
        'expected_delivery_date',
        'delivery_date',
        'lr_date',
        'return_date',
        'quotation_date',
        'valid_until',
        'transport_doc_date',
        'po_date',
        'due_date',
        'payment_date',
        'cheque_date',
        'exp_date',
        'dob',
        'anniversary',
    ];

    /**
     * Common request keys that represent datetime values (which must preserve time).
     */
    protected array $dateTimeKeys = [
        'bill_date',
        'eway_valid_until',
    ];

    /**
     * Handle an incoming request and normalize any incoming user date inputs to MySQL YYYY-MM-DD.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $all = $request->all();
        if (!empty($all)) {
            $normalized = $this->normalizeArray($all);
            if ($normalized !== $all) {
                $request->merge($normalized);
            }
        }

        return $next($request);
    }

    /**
     * Recursively normalize date inputs in request arrays.
     */
    protected function normalizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalizeArray($value);
            } elseif (is_string($value) && $this->isDateTimeKey($key)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    $norm = DateHelper::normalizeDateTime($trimmed);
                    if ($norm !== null) {
                        $data[$key] = $norm;
                    }
                }
            } elseif (is_string($value) && $this->isDateKey($key)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    $norm = DateHelper::normalize($trimmed);
                    if ($norm !== null) {
                        $data[$key] = $norm;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Determine if a given key is a candidate for datetime normalization.
     */
    protected function isDateTimeKey(string|int $key): bool
    {
        if (!is_string($key)) {
            return false;
        }

        return in_array(strtolower($key), $this->dateTimeKeys, true);
    }

    /**
     * Determine if a given key is a candidate for date normalization.
     */
    protected function isDateKey(string|int $key): bool
    {
        if (!is_string($key)) {
            return false;
        }

        $lower = strtolower($key);
        if ($this->isDateTimeKey($lower)) {
            return false;
        }

        if (in_array($lower, $this->dateKeys, true)) {
            return true;
        }

        // Keys ending in _date or containing date/exp_date (excluding candidates, consolidated, update_rate)
        if (preg_match('/^(candidates?|consolidated|update_rate)/i', $lower)) {
            return false;
        }

        if (str_ends_with($lower, '_date') || str_ends_with($lower, 'date') || str_contains($lower, 'exp_date')) {
            return true;
        }

        return false;
    }
}
