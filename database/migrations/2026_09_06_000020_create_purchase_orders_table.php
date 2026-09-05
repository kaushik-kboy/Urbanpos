<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->date('po_date');
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->enum('purchase_type', ['Local', 'Interstate'])->default('Local');
            $table->enum('c_form', ['Against C-Form', 'No Forms'])->default('Against C-Form');
            $table->decimal('item_disc_amount', 12, 2)->default(0);
            $table->decimal('disc_percent', 5, 2)->default(0);
            $table->decimal('disc_amount', 12, 2)->default(0);
            $table->decimal('freight', 12, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('scheme_item_disc_amt', 12, 2)->default(0);
            $table->decimal('other_disc_amt', 12, 2)->default(0);
            $table->decimal('total_gst', 12, 2)->default(0);
            $table->decimal('total_extra_cess', 12, 2)->default(0);
            $table->decimal('total_qty', 12, 3)->default(0);
            $table->decimal('total_weight', 12, 3)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['Open', 'Closed', 'Cancelled'])->default('Open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
