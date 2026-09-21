<?php

namespace App\Models;

use App\Concerns\HasPostingLifecycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesBill extends Model
{
    use HasFactory, HasPostingLifecycle, \App\Traits\HasCustomFields;

    protected $fillable = [
        'bill_number', 'bill_date', 'customer_id', 'branch_id', 'sales_delivery_note_id', 'till_session_id', 'invoice_type', 'delivery_type',
        'delivery_time', 'sales_type', 'payment_type', 'item_disc_amount', 'disc_percent',
        'disc_amount', 'round_off', 'total_gst', 'total_cgst', 'total_sgst', 'total_igst',
        'total_extra_cess', 'gst_calamity_cess',
        'total_qty', 'total_weight', 'total', 'remarks', 'message',
        'status', 'posting_key',
        'eway_bill_no', 'eway_bill_date', 'eway_valid_until',
        'transporter_id', 'transporter_name', 'transport_mode',
        'transport_doc_no', 'transport_doc_date',
        'vehicle_no', 'vehicle_type', 'transport_distance', 'eway_status',
        'irn', 'ack_no', 'ack_date', 'signed_qr_code', 'signed_invoice',
        'einvoice_status', 'einvoice_error', 'einvoice_synced_at',
    ];

    protected $casts = [
        'bill_date' => 'datetime',
        'eway_bill_date' => 'datetime',
        'eway_valid_until' => 'datetime',
        'transport_doc_date' => 'date',
        'ack_date' => 'datetime',
        'einvoice_synced_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(SalesDeliveryNote::class, 'sales_delivery_note_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesBillItem::class);
    }

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function tillSession(): BelongsTo
    {
        return $this->belongsTo(TillSession::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalesBillPayment::class);
    }

    public function settlementItems(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(BillSettlementItem::class, 'billable');
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyPoint::class);
    }

    public function pointsEarned(): float
    {
        return (float) $this->loyaltyTransactions()->where('type', 'Earned')->sum('points');
    }

    public function pointsRedeemed(): float
    {
        return (float) $this->loyaltyTransactions()->where('type', 'Redeemed')->sum('points');
    }

    public function hasEwayBill(): bool
    {
        return !empty($this->eway_bill_no);
    }

    public function requiresEwayBill(): bool
    {
        // E-Way Bill is typically mandatory for consignment value > ₹50,000 or interstate deliveries
        return (float) $this->total >= 50000 || !empty($this->vehicle_no) || !empty($this->transporter_name);
    }

    public function hasIrn(): bool
    {
        return !empty($this->irn);
    }

    public function isEinvoiceCompleted(): bool
    {
        return $this->einvoice_status === 'Completed' && !empty($this->irn);
    }

    public function isEinvoiceFailed(): bool
    {
        return $this->einvoice_status === 'Failed';
    }

    public function requiresEinvoice(): bool
    {
        // Eligible if total >= ₹50,000 or Customer is registered (B2B with GSTIN)
        return (float) $this->total >= 50000 || !empty($this->customer?->gst_no);
    }
}
