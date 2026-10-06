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
            $table->string('invoice_format')->default('thermal')->after('document_type'); // 'thermal', 'a4_gst'
            $table->string('header_layout')->default('logo_left_address_below')->after('store_name'); // 'logo_left_address_below', 'centered', 'logo_left_address_right', 'logo_right_address_left'
            $table->string('accent_color')->default('#1e40af')->after('header_layout'); // Default Navy Blue (#1e40af), Orange (#ea580c), Slate (#1f2937), etc.
            $table->boolean('show_ship_to')->default(false)->after('show_customer_pet_name');
            $table->boolean('show_tax_summary_table')->default(true)->after('show_tax_breakup');
            $table->boolean('show_payment_details')->default(true)->after('show_discount');
            $table->boolean('show_signature_box')->default(true)->after('show_barcode');
            $table->boolean('show_support_qr')->default(false)->after('show_signature_box');
            $table->string('support_qr_payload')->nullable()->after('show_support_qr');
            $table->text('terms_conditions')->nullable()->after('footer_policy');
            $table->text('compliance_notes')->nullable()->after('terms_conditions');
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
