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
        Schema::table('receipt_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('receipt_settings', 'invoice_format')) {
                $table->string('invoice_format')->default('thermal')->after('document_type');
            }
            if (!Schema::hasColumn('receipt_settings', 'header_layout')) {
                $table->string('header_layout')->default('logo_left_address_below')->after('store_name');
            }
            if (!Schema::hasColumn('receipt_settings', 'accent_color')) {
                $table->string('accent_color')->default('#1e40af')->after('header_layout');
            }
            if (!Schema::hasColumn('receipt_settings', 'show_ship_to')) {
                $table->boolean('show_ship_to')->default(false)->after('show_customer_pet_name');
            }
            if (!Schema::hasColumn('receipt_settings', 'show_tax_summary_table')) {
                $table->boolean('show_tax_summary_table')->default(true)->after('show_tax_breakup');
            }
            if (!Schema::hasColumn('receipt_settings', 'show_payment_details')) {
                $table->boolean('show_payment_details')->default(true)->after('show_discount');
            }
            if (!Schema::hasColumn('receipt_settings', 'show_signature_box')) {
                $table->boolean('show_signature_box')->default(true)->after('show_barcode');
            }
            if (!Schema::hasColumn('receipt_settings', 'show_support_qr')) {
                $table->boolean('show_support_qr')->default(false)->after('show_signature_box');
            }
            if (!Schema::hasColumn('receipt_settings', 'support_qr_payload')) {
                $table->string('support_qr_payload')->nullable()->after('show_support_qr');
            }
            if (!Schema::hasColumn('receipt_settings', 'terms_conditions')) {
                $table->text('terms_conditions')->nullable()->after('footer_policy');
            }
            if (!Schema::hasColumn('receipt_settings', 'compliance_notes')) {
                $table->text('compliance_notes')->nullable()->after('terms_conditions');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_format',
                'header_layout',
                'accent_color',
                'show_ship_to',
                'show_tax_summary_table',
                'show_payment_details',
                'show_signature_box',
                'show_support_qr',
                'support_qr_payload',
                'terms_conditions',
                'compliance_notes',
            ]);
        });
    }
};
