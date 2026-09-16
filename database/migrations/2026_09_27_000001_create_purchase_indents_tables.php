<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_indents', function (Blueprint $table) {
            $table->id();
            $table->string('indent_number')->unique();
            $table->date('indent_date');
            $table->date('required_by_date')->nullable();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('requested_by_id')->constrained('users');
            $table->string('department')->nullable();
            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])->default('Medium');
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Converted', 'Cancelled'])->default('Pending');
            $table->decimal('total_requested_qty', 12, 3)->default(0);
            $table->decimal('total_approved_qty', 12, 3)->default(0);
            $table->decimal('total_estimated_amount', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->string('posting_key')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('purchase_indent_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_indent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->decimal('current_stock', 12, 3)->default(0);
            $table->decimal('requested_qty', 12, 3)->default(0);
            $table->decimal('approved_qty', 12, 3)->nullable();
            $table->decimal('estimated_cost', 14, 4)->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        if (!Schema::hasColumn('purchase_orders', 'purchase_indent_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreignId('purchase_indent_id')->nullable()->after('branch_id')->constrained('purchase_indents')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_orders', 'purchase_indent_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropForeign(['purchase_indent_id']);
                $table->dropColumn('purchase_indent_id');
            });
        }

        Schema::dropIfExists('purchase_indent_items');
        Schema::dropIfExists('purchase_indents');
    }
};
