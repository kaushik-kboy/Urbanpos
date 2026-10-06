<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptSetting extends Model
{
    use HasFactory;

    protected $table = 'receipt_settings';

    protected $fillable = [
        'document_type',
        'branch_id',
        'invoice_format',
        'header_layout',
        'accent_color',
        'store_name',
        'tagline',
        'logo_path',
        'logo_width',
        'show_logo',
        'header_address',
        'phone',
        'phone_alt',
        'email',
        'gstin',
        'show_customer_pet_name',
        'show_ship_to',
        'show_hsn_code',
        'show_tax_breakup',
        'show_tax_summary_table',
        'show_discount',
        'show_payment_details',
        'show_upi_qr',
        'upi_id',
        'upi_payee_name',
        'show_barcode',
        'show_signature_box',
        'show_support_qr',
        'support_qr_payload',
        'paper_size',
        'font_size',
        'terms_conditions',
        'compliance_notes',
        'footer_policy',
        'footer_note',
        'custom_css',
    ];

    protected $casts = [
        'show_logo'              => 'boolean',
        'logo_width'             => 'integer',
        'show_customer_pet_name' => 'boolean',
        'show_ship_to'           => 'boolean',
        'show_hsn_code'          => 'boolean',
        'show_tax_breakup'       => 'boolean',
        'show_tax_summary_table' => 'boolean',
        'show_discount'          => 'boolean',
        'show_payment_details'   => 'boolean',
        'show_upi_qr'            => 'boolean',
        'show_barcode'           => 'boolean',
        'show_signature_box'     => 'boolean',
        'show_support_qr'        => 'boolean',
    ];

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * All supported document types with their label and icon.
     * Add a new entry here to instantly support a new document type
     * in the Receipt Designer — no other code changes needed.
     */
    public static function supportedTypes(): array
    {
        return [
            'sales_bill' => [
                'label' => 'Sales Bill / Invoice',
                'icon'  => 'fas fa-file-invoice',
                'color' => 'primary',
            ],
            'sales_return' => [
                'label' => 'Sales Return',
                'icon'  => 'fas fa-undo-alt',
                'color' => 'danger',
            ],
            'purchase_invoice' => [
                'label' => 'Purchase Invoice',
                'icon'  => 'fas fa-shopping-cart',
                'color' => 'success',
            ],
            'purchase_return' => [
                'label' => 'Purchase Return',
                'icon'  => 'fas fa-reply',
                'color' => 'secondary',
            ],
            'stock_transfer' => [
                'label' => 'Stock Transfer Note',
                'icon'  => 'fas fa-exchange-alt',
                'color' => 'warning',
            ],
        ];
    }

    /**
     * Get or create the receipt settings row for a specific document type and branch.
     * Falls back to general defaults or branch master info if not yet configured.
     */
    public static function forDocument(string $documentType = 'sales_bill', ?int $branchId = null): self
    {
        if ($branchId && ! Branch::whereKey($branchId)->exists()) {
            $branchId = null;
        }

        if ($branchId) {
            $specific = static::where('document_type', $documentType)
                ->where('branch_id', $branchId)
                ->first();

            if ($specific) {
                return $specific;
            }
        }

        // Get base template for this document_type (or sales_bill)
        $base = static::where('document_type', $documentType)->whereNull('branch_id')->first()
            ?? static::where('document_type', $documentType)->first()
            ?? static::where('document_type', 'sales_bill')->first();

        // Branch info if branchId provided
        $branch = $branchId ? Branch::find($branchId) : null;
        $branchAddress = null;
        if ($branch) {
            $addrParts = array_filter([$branch->address_line1, $branch->address_line2, $branch->city, $branch->state, $branch->postal_code]);
            $branchAddress = !empty($addrParts) ? implode("\n", $addrParts) : null;
        }

        return static::firstOrCreate(
            [
                'document_type' => $documentType,
                'branch_id'     => $branchId,
            ],
            [
                'store_name'             => $branch?->name                 ?? ($base?->store_name             ?? 'URBAN POS'),
                'tagline'                => $base?->tagline                ?? 'Complete Pet Care & Supplies',
                'logo_path'              => $base?->logo_path              ?? null,
                'logo_width'             => $base?->logo_width             ?? 120,
                'show_logo'              => $base?->show_logo              ?? false,
                'header_address'         => $branchAddress                 ?? ($base?->header_address         ?? null),
                'phone'                  => $branch?->phone ?: ($base?->phone ?? null),
                'phone_alt'              => $branch?->mobile ?: ($base?->phone_alt ?? null),
                'email'                  => $branch?->email ?: ($base?->email ?? null),
                'gstin'                  => $branch?->gst_no ?: ($base?->gstin ?? null),
                'show_customer_pet_name' => false,
                'show_hsn_code'          => true,
                'show_tax_breakup'       => $documentType === 'sales_bill',
                'show_discount'          => false,
                'show_upi_qr'            => false,
                'upi_id'                 => $branch?->upi_id ?: ($base?->upi_id ?? null),
                'upi_payee_name'         => $branch?->upi_payee_name ?: ($base?->upi_payee_name ?? null),
                'show_barcode'           => false,
                'paper_size'             => in_array($documentType, ['stock_transfer', 'purchase_invoice']) ? 'a4' : ($base?->paper_size ?? '80mm'),
                'font_size'              => $base?->font_size              ?? 'normal',
                'footer_policy'          => $base?->footer_policy          ?? null,
                'footer_note'            => $base?->footer_note            ?? null,
                'custom_css'             => $base?->custom_css             ?? null,
            ]
        );
    }

    /**
     * Legacy alias — returns sales_bill settings.
     * Kept for backward compatibility with existing print templates.
     */
    public static function current(?int $branchId = null): self
    {
        return static::forDocument('sales_bill', $branchId);
    }

    /**
     * Generate dynamic UPI payment payload & QR image URL.
     */
    public function getUpiQrUrl(float $amount, string $billNumber): string
    {
        $upiId = $this->upi_id ?: '7383056626@okbizaxis';
        $payeeName = $this->upi_payee_name ?: $this->store_name ?: 'Urban Pets';
        $formattedAmount = number_format($amount, 2, '.', '');

        $upiString = "upi://pay?pa=" . rawurlencode($upiId)
            . "&pn=" . rawurlencode($payeeName)
            . "&am=" . $formattedAmount
            . "&tr=" . rawurlencode($billNumber)
            . "&tn=" . rawurlencode("Bill {$billNumber}")
            . "&cu=INR";

        return "https://api.qrserver.com/v1/create-qr-code/?size=120x120&margin=4&data=" . urlencode($upiString);
    }

    /**
     * Determine if A4/A5 GST full-page invoice template is active.
     */
    public function isA4GstInvoice(): bool
    {
        return ($this->invoice_format === 'a4_gst') || in_array($this->paper_size, ['a4', 'a5']);
    }

    /**
     * Get validated header layout identifier.
     */
    public function getHeaderLayout(): string
    {
        $valid = ['logo_left_address_below', 'centered', 'logo_left_address_right', 'logo_right_address_left'];
        return in_array($this->header_layout, $valid) ? $this->header_layout : 'logo_left_address_below';
    }

    /**
     * Get theme accent color hex code.
     */
    public function getAccentColor(): string
    {
        return $this->accent_color ?: '#1e40af';
    }

    /**
     * Generate Support QR code image URL.
     */
    public function getSupportQrUrl(): string
    {
        $payload = $this->support_qr_payload ?: ($this->phone ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $this->phone) : 'https://urbanpets.in');
        return "https://api.qrserver.com/v1/create-qr-code/?size=100x100&margin=2&data=" . urlencode($payload);
    }
}
