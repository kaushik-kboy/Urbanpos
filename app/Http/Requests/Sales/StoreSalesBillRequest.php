<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for Sales Bill creation and update.
 *
 * Centralises header-level validation rules so SalesBillController::validateData()
 * can delegate the initial rule resolution here rather than keeping raw rule arrays
 * inline. Business-logic checks (duplicate bill detection, stock checks, etc.)
 * remain in the controller's validateData() method where they access services and
 * DB state that belong in the application layer.
 *
 * Usage (future refactor):
 *   public function store(StoreSalesBillRequest $request) { ... }
 *   public function update(StoreSalesBillRequest $request, SalesBill $salesBill) { ... }
 */
class StoreSalesBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $now = now()->addMinutes(2)->format('Y-m-d H:i:s');

        return [
            // ── Header ────────────────────────────────────────────────────
            'bill_number'              => ['nullable', 'string', 'max:100'],
            'bill_date'                => ['required', 'date', "before_or_equal:{$now}"],
            'customer_id'              => ['required', 'exists:customers,id'],
            'branch_id'                => ['required', 'exists:branches,id'],
            'sales_delivery_note_id'   => ['nullable', 'exists:sales_delivery_notes,id'],
            'invoice_type'             => ['required', 'in:Retail Invoice,Tax Invoice,Exempted'],
            'delivery_type'            => ['required', 'string', 'max:255'],
            'delivery_time'            => ['nullable', 'date_format:H:i'],
            'sales_type'               => ['required', 'in:Local,Interstate'],
            'payment_type'             => ['nullable', 'string', 'max:255'],
            'till_session_id'          => ['nullable', 'exists:till_sessions,id'],
            'round_off'                => ['nullable', 'numeric'],
            'total_extra_cess'         => ['nullable', 'numeric', 'min:0'],
            'gst_calamity_cess'        => ['nullable', 'numeric', 'min:0'],
            'total_weight'             => ['nullable', 'numeric', 'min:0'],
            'remarks'                  => ['nullable', 'string'],
            'message'                  => ['nullable', 'string'],
            'posting_key'              => ['nullable', 'string', 'max:100'],

            // ── Line Items ────────────────────────────────────────────────
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.item_id'          => ['required', 'exists:items,id'],
            'items.*.exp_date'         => ['nullable', 'date'],
            'items.*.qty'              => ['required', 'numeric', 'min:0.001'],
            'items.*.sell_price'       => ['required', 'numeric', 'min:0'],
            'items.*.mrp'              => ['nullable', 'numeric', 'min:0'],
            'items.*.disc_percent'     => ['nullable', 'numeric', 'min:0'],
            'items.*.disc_amount'      => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_percent'      => ['nullable', 'numeric', 'min:0'],

            // ── Payments ──────────────────────────────────────────────────
            'payments'                        => ['nullable', 'array'],
            'payments.*.tender_type_id'       => ['required_with:payments', 'exists:tender_types,id'],
            'payments.*.tender_type_value_id' => ['nullable', 'exists:tender_type_values,id'],
            'payments.*.amount'               => ['required_with:payments', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'bill_date.before_or_equal' => 'Future date and time is not allowed for Bill Date.',
        ];
    }
}
