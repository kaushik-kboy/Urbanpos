<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_STOCK_LEDGER_ENUM = [
        'OPENING', 'PURCHASE_RECEIPT', 'PURCHASE_RETURN', 'SALE', 'SALE_RETURN',
        'TRANSFER_OUT', 'TRANSFER_TRANSIT', 'TRANSFER_IN', 'DAMAGE', 'EXPIRY',
        'SHORTAGE', 'EXCESS', 'CORRECTION', 'REPACK', 'KIT_ASSEMBLY', 'KIT_DISASSEMBLY',
    ];

    private const NEW_STOCK_LEDGER_ENUM = [
        'OPENING', 'PURCHASE_RECEIPT', 'PURCHASE_RETURN', 'SALE', 'SALE_RETURN',
        'TRANSFER_OUT', 'TRANSFER_TRANSIT', 'TRANSFER_IN', 'DAMAGE', 'EXPIRY',
        'SHORTAGE', 'EXCESS', 'CORRECTION', 'REPACK', 'KIT_ASSEMBLY', 'KIT_DISASSEMBLY',
        'SALES_DELIVERY',
    ];

    public function up(): void
    {
        // Add SALES_DELIVERY to stock_ledger movement_type enum
        // MODIFY ENUM is MySQL/MariaDB only — SQLite (used in CI testing) doesn't support this syntax
        if (DB::getDriverName() !== 'sqlite') {
            $list = implode(',', array_map(fn ($v) => "'{$v}'", self::NEW_STOCK_LEDGER_ENUM));
            DB::statement("ALTER TABLE stock_ledger MODIFY movement_type ENUM({$list}) NOT NULL");
        }

        Schema::create('sales_delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number')->unique();
            $table->date('delivery_date');
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_bill_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_no')->nullable();
            $table->string('transporter_name')->nullable();
            $table->string('vehicle_no')->nullable();
            $table->string('lr_no')->nullable();
            $table->date('lr_date')->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('total_ordered_qty', 12, 3)->default(0);
            $table->decimal('total_dispatched_qty', 12, 3)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->enum('status', ['Dispatched', 'Invoiced', 'Cancelled'])->default('Dispatched');
            $table->text('remarks')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('posting_key')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('sales_delivery_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_delivery_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->decimal('ordered_qty', 12, 3)->default(0);
            $table->decimal('dispatched_qty', 12, 3)->default(0);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('cost_at_dispatch', 14, 4)->default(0);
            $table->decimal('mrp', 14, 2)->nullable();
            $table->string('batch_no')->nullable();
            $table->date('exp_date')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        if (!Schema::hasColumn('sales_bills', 'sales_delivery_note_id')) {
            Schema::table('sales_bills', function (Blueprint $table) {
                $table->foreignId('sales_delivery_note_id')->nullable()->after('branch_id')->constrained('sales_delivery_notes')->nullOnDelete();
            });
        }

        if (Schema::hasTable('sales_order_items') && !Schema::hasColumn('sales_order_items', 'dispatched_qty')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->decimal('dispatched_qty', 12, 3)->default(0)->after('qty');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_order_items') && Schema::hasColumn('sales_order_items', 'dispatched_qty')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->dropColumn('dispatched_qty');
            });
        }

        if (Schema::hasColumn('sales_bills', 'sales_delivery_note_id')) {
            Schema::table('sales_bills', function (Blueprint $table) {
                $table->dropForeign(['sales_delivery_note_id']);
                $table->dropColumn('sales_delivery_note_id');
            });
        }

        Schema::dropIfExists('sales_delivery_note_items');
        Schema::dropIfExists('sales_delivery_notes');

        // Revert stock_ledger movement_type enum
        if (DB::getDriverName() !== 'sqlite') {
            $list = implode(',', array_map(fn ($v) => "'{$v}'", self::OLD_STOCK_LEDGER_ENUM));
            DB::statement("ALTER TABLE stock_ledger MODIFY movement_type ENUM({$list}) NOT NULL");
        }
    }
};
