<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('daily_sales_summaries')) {
            Schema::create('daily_sales_summaries', function (Blueprint $table) {
                $table->id();
                $table->string('store_id', 50)->nullable()->index();
                $table->string('store_name')->nullable()->index();
                $table->foreignId('branch_id')->nullable()->index();
                $table->date('summary_date')->index();
                $table->decimal('bill_amount', 14, 2)->default(0);
                $table->decimal('tax', 12, 2)->default(0);
                $table->decimal('disc_amount', 12, 2)->default(0);
                $table->decimal('scheme_amount', 12, 2)->default(0);
                $table->decimal('cash', 12, 2)->default(0);
                $table->decimal('card', 12, 2)->default(0);
                $table->decimal('cheque', 12, 2)->default(0);
                $table->decimal('coupon', 12, 2)->default(0);
                $table->decimal('wallet_amt', 12, 2)->default(0);
                $table->decimal('credit', 12, 2)->default(0);
                $table->decimal('due', 12, 2)->default(0);
                $table->decimal('compliment', 12, 2)->default(0);
                $table->decimal('approval', 12, 2)->default(0);
                $table->decimal('advance_adjusted', 12, 2)->default(0);
                $table->decimal('rounded_off', 10, 2)->default(0);
                $table->decimal('profit', 12, 2)->default(0);
                $table->decimal('item_discount', 12, 2)->default(0);
                $table->decimal('bill_discount', 12, 2)->default(0);
                $table->decimal('freight', 12, 2)->default(0);
                $table->integer('total_bills')->default(0);
                $table->decimal('gst_tax_amt', 12, 2)->default(0);
                $table->decimal('sgst_tax_amt', 12, 2)->default(0);
                $table->decimal('cgst_tax_amt', 12, 2)->default(0);
                $table->decimal('igst_tax_amt', 12, 2)->default(0);
                $table->decimal('gst_cess_amt', 12, 2)->default(0);
                $table->decimal('redeemed_point', 12, 2)->default(0);
                $table->timestamps();

                $table->unique(['branch_id', 'summary_date'], 'uq_branch_summary_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_sales_summaries');
    }
};
