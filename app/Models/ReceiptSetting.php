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
        'show_hsn_code',
        'show_tax_breakup',
        'show_discount',
        'show_upi_qr',
        'upi_id',
        'upi_payee_name',
        'show_barcode',
        'paper_size',
        'font_size',
        'footer_policy',
        'footer_note',
        'custom_css',
    ];

    protected $casts = [
        'show_logo'              => 'boolean',
        'logo_width'             => 'integer',
        'show_customer_pet_name' => 'boolean',
        'show_hsn_code'          => 'boolean',
        'show_tax_breakup'       => 'boolean',
        'show_discount'          => 'boolean',
        'show_upi_qr'            => 'boolean',
        'show_barcode'           => 'boolean',
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
            'stock_transfer' => [
                'label' => 'Stock Transfer Note',
                'icon'  => 'fas fa-exchange-alt',
                'color' => 'warning',
            ],
            'purchase_invoice' => [
                'label' => 'Purchase Invoice',
                'icon'  => 'fas fa-shopping-cart',
                'color' => 'success',
            ],
        ];
    }

    /**
     * Get or create the receipt settings row for a specific document type and branch.
     * Falls back to general defaults or branch master info if not yet configured.
     */
    public static function forDocument(string $documentType = 'sales_bill', ?int $branchId = null): self
    {
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
}
