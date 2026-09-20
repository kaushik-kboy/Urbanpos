<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptSetting extends Model
{
    use HasFactory;

    protected $table = 'receipt_settings';

    protected $fillable = [
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

    /**
     * Get or create the singleton receipt settings row.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'store_name'             => 'URBAN PETS',
            'tagline'                => 'Complete Pet Care & Supplies',
            'logo_path'              => null,
            'logo_width'             => 120,
            'show_logo'              => false,
            'header_address'         => null, // Falls back to branch address if null
            'phone'                  => '7383056626',
            'phone_alt'              => null,
            'email'                  => null,
            'gstin'                  => null, // Falls back to branch GST if null
            'show_customer_pet_name' => true,
            'show_hsn_code'          => true,
            'show_tax_breakup'       => true,
            'show_discount'          => true,
            'show_upi_qr'            => true,
            'upi_id'                 => '7383056626@okbizaxis',
            'upi_payee_name'         => 'Urban Pets',
            'show_barcode'           => true,
            'paper_size'             => '80mm',
            'font_size'              => 'normal',
            'footer_policy'          => "Exchange valid within 7 days with original bill.\nNo return on opened treats, medicines or frozen food.",
            'footer_note'            => "Thank you for shopping at Urban Pets!\n*** Have an Awesome Day! ***",
            'custom_css'             => null,
        ]);
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
