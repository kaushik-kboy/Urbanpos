<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('receipt_settings', 'document_title')) {
                $table->string('document_title', 100)->nullable()->after('header_office_title');
            }
        });

        // Seed sensible initial titles for existing rows
        DB::table('receipt_settings')->where('document_type', 'sales_bill')->whereNull('document_title')->update(['document_title' => 'TAX INVOICE']);
        DB::table('receipt_settings')->where('document_type', 'sales_return')->whereNull('document_title')->update(['document_title' => 'CREDIT NOTE']);
        DB::table('receipt_settings')->where('document_type', 'purchase_invoice')->whereNull('document_title')->update(['document_title' => 'PURCHASE INVOICE']);
        DB::table('receipt_settings')->where('document_type', 'purchase_return')->whereNull('document_title')->update(['document_title' => 'DEBIT NOTE']);
        DB::table('receipt_settings')->where('document_type', 'stock_transfer')->whereNull('document_title')->update(['document_title' => 'STOCK TRANSFER']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            if (Schema::hasColumn('receipt_settings', 'document_title')) {
                $table->dropColumn('document_title');
            }
        });
    }
};
