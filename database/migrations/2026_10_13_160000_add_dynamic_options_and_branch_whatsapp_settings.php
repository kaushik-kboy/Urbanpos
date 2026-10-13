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
        // 1. Add extra dynamic show/hide options to receipt_settings table
        Schema::table('receipt_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('receipt_settings', 'show_order_ref')) {
                $table->boolean('show_order_ref')->default(true)->after('show_ship_to');
            }
            if (!Schema::hasColumn('receipt_settings', 'show_supply_details')) {
                $table->boolean('show_supply_details')->default(true)->after('show_order_ref');
            }
            if (!Schema::hasColumn('receipt_settings', 'show_item_code')) {
                $table->boolean('show_item_code')->default(true)->after('show_hsn_code');
            }
            if (!Schema::hasColumn('receipt_settings', 'show_mrp')) {
                $table->boolean('show_mrp')->default(true)->after('show_item_code');
            }
            if (!Schema::hasColumn('receipt_settings', 'show_amount_in_words')) {
                $table->boolean('show_amount_in_words')->default(true)->after('show_tax_summary_table');
            }
            if (!Schema::hasColumn('receipt_settings', 'show_compliance_notes')) {
                $table->boolean('show_compliance_notes')->default(true)->after('show_payment_details');
            }
            if (!Schema::hasColumn('receipt_settings', 'show_terms_conditions')) {
                $table->boolean('show_terms_conditions')->default(true)->after('terms_conditions');
            }
            if (!Schema::hasColumn('receipt_settings', 'show_total_qty')) {
                $table->boolean('show_total_qty')->default(true)->after('show_tax_breakup');
            }
        });

        // 2. Add branch_id and custom_message to whats_app_settings table
        Schema::table('whats_app_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('whats_app_settings', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('id');
                $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
                $table->unique('branch_id', 'whats_app_settings_branch_unique');
            }
            if (!Schema::hasColumn('whats_app_settings', 'custom_message')) {
                $table->text('custom_message')->nullable()->after('footer_message');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            $cols = [
                'show_order_ref', 'show_supply_details', 'show_item_code', 'show_mrp',
                'show_amount_in_words', 'show_compliance_notes', 'show_terms_conditions', 'show_total_qty'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('receipt_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('whats_app_settings', function (Blueprint $table) {
            if (Schema::hasColumn('whats_app_settings', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropUnique('whats_app_settings_branch_unique');
                $table->dropColumn('branch_id');
            }
            if (Schema::hasColumn('whats_app_settings', 'custom_message')) {
                $table->dropColumn('custom_message');
            }
        });
    }
};
