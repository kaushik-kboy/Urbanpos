<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Makes receipt_settings multi-document-type aware.
 *
 * Before: single global row (id=1) shared by all document types.
 * After:  one row per document_type (sales_bill, stock_transfer, …).
 *
 * Existing row (if any) is tagged as 'sales_bill' so all current data
 * is preserved with zero data loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            // Add document_type with a default so existing rows get tagged
            $table->string('document_type', 50)->default('sales_bill')->after('id');
        });

        // Tag existing global row as sales_bill (idempotent)
        DB::table('receipt_settings')
            ->whereNull('document_type')
            ->orWhere('document_type', '')
            ->update(['document_type' => 'sales_bill']);

        // Add unique constraint AFTER data is tagged
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->unique('document_type', 'receipt_settings_document_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->dropUnique('receipt_settings_document_type_unique');
            $table->dropColumn('document_type');
        });
    }
};
