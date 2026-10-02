<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for Purchase Invoice creation and update.
 *
 * Centralises header-level and line-item validation rules so
 * PurchaseInvoiceController::validateData() can reference these in future
 * refactoring without keeping raw rule arrays inline.
 *
 * Business-logic checks (duplicate GRN detection, credit limit, sell-price
 * constraints) remain in the controller's validateData() method where they
 * access services and DB state.
 *
 * Usage (future refactor):
 *   public function store(StorePurchaseInvoiceRequest $request) { ... }
 *   public function update(StorePurchaseInvoiceRequest $request, PurchaseInvoice $purchaseInvoice) { ... }
 */
class StorePurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Header ────────────────────────────────────────────────────
            'header.invoice_number'        => ['nullable', 'string', 'max:100'],
            'header.invoice_date'          => ['required', 'date'],
            'header.supplier_id'           => ['required', 'exists:suppliers,id'],
            'header.branch_id'             => ['required', 'exists:branches,id'],
            'header.purchase_order_id'     => ['nullable', 'exists:purchase_orders,id'],
            'header.purchase_receipt_note_id' => ['nullable', 'exists:purchase_receipt_notes,id'],
            'header.purchase_type'         => ['required', 'in:Local,Interstate,Import'],
            'header.grn_number'            => ['nullable', 'string', 'max:50'],
            'header.grn_date'              => ['nullable', 'date'],
            'header.supplier_inv_no'       => ['nullable', 'string', 'max:100'],
            'header.supplier_inv_date'     => ['nullable', 'date'],
            'header.supplier_inv_amount'   => ['nullable', 'numeric', 'min:0'],
            'header.freight'               => ['nullable', 'numeric', 'min:0'],
            'header.round_off'             => ['nullable', 'numeric'],
            'header.total_extra_cess'      => ['nullable', 'numeric', 'min:0'],
            'header.tcs_amount'            => ['nullable', 'numeric', 'min:0'],
            'header.total_weight'          => ['nullable', 'numeric', 'min:0'],
            'header.remarks'               => ['nullable', 'string'],
            'header.message'               => ['nullable', 'string'],
            'header.posting_key'           => ['nullable', 'string', 'max:100'],

            // ── Line Items ────────────────────────────────────────────────
            'items'                        => ['required', 'array', 'min:1'],
            'items.*.item_id'              => ['required', 'exists:items,id'],
            'items.*.exp_date'             => ['nullable', 'date'],
            'items.*.qty'                  => ['required', 'numeric', 'min:0.001'],
            'items.*.free_qty'             => ['nullable', 'numeric', 'min:0'],
            'items.*.cost_price'           => ['required', 'numeric', 'min:0'],
            'items.*.sell_price'           => ['nullable', 'numeric', 'min:0'],
            'items.*.mrp'                  => ['nullable', 'numeric', 'min:0'],
            'items.*.disc_percent'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.disc_amount'          => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_percent'          => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.gst_tax_amount'       => ['nullable', 'numeric', 'min:0'],
            'scheme_item_disc_percent'     => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            if (is_array($items)) {
                foreach ($items as $idx => $line) {
                    $qty = (float) ($line['qty'] ?? 0);
                    $costPrice = (float) ($line['cost_price'] ?? 0);
                    $discAmount = (float) ($line['disc_amount'] ?? 0);
                    $gross = round($qty * $costPrice, 2);
                    if ($gross > 0 && $discAmount > $gross) {
                        $validator->errors()->add("items.{$idx}.disc_amount", "Row #" . ($idx + 1) . ": Discount amount (₹{$discAmount}) cannot exceed item gross total (₹{$gross}).");
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.*.disc_percent.max'     => 'Discount % cannot exceed 100%.',
            'scheme_item_disc_percent.max' => 'Scheme Item Discount % cannot exceed 100%.',
            'header.invoice_date.required' => 'Invoice date is required.',
            'header.supplier_id.required'  => 'Supplier is required.',
            'header.branch_id.required'    => 'Branch is required.',
            'header.purchase_type.required' => 'Purchase type is required.',
            'items.required'               => 'At least one line item is required.',
            'items.min'                    => 'At least one line item is required.',
        ];
    }
}
