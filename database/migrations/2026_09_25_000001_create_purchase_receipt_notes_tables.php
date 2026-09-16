<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipt_notes', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->date('receipt_date');
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_challan_no')->nullable();
            $table->date('supplier_challan_date')->nullable();
            $table->string('vehicle_no')->nullable();
            $table->string('transporter_name')->nullable();
            $table->decimal('total_ordered_qty', 12, 3)->default(0);
            $table->decimal('total_received_qty', 12, 3)->default(0);
            $table->decimal('total_accepted_qty', 12, 3)->default(0);
            $table->decimal('total_rejected_qty', 12, 3)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->enum('status', ['Received', 'Invoiced', 'Cancelled'])->default('Received');
            $table->text('remarks')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('posting_key')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('purchase_receipt_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->decimal('ordered_qty', 12, 3)->default(0);
            $table->decimal('received_qty', 12, 3)->default(0);
            $table->decimal('accepted_qty', 12, 3)->default(0);
            $table->decimal('rejected_qty', 12, 3)->default(0);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->decimal('mrp', 14, 2)->nullable();
            $table->string('batch_no')->nullable();
            $table->date('exp_date')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        if (!Schema::hasColumn('purchase_invoices', 'purchase_receipt_note_id')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->foreignId('purchase_receipt_note_id')->nullable()->after('purchase_order_id')->constrained('purchase_receipt_notes')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_invoices', 'purchase_receipt_note_id')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->dropForeign(['purchase_receipt_note_id']);
                $table->dropColumn('purchase_receipt_note_id');
            });
        }

        Schema::dropIfExists('purchase_receipt_note_items');
        Schema::dropIfExists('purchase_receipt_notes');
    }
};
