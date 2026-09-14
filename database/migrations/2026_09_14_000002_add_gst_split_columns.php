<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $itemTables = ['purchase_invoice_items', 'sales_bill_items', 'sales_return_items'];
    private array $headerTables = ['purchase_invoices', 'sales_bills', 'sales_returns'];

    public function up(): void
    {
        foreach ($this->itemTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('cgst_amount', 12, 2)->default(0)->after('gst_tax_amount');
                $t->decimal('sgst_amount', 12, 2)->default(0)->after('cgst_amount');
                $t->decimal('igst_amount', 12, 2)->default(0)->after('sgst_amount');
            });
        }

        foreach ($this->headerTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('total_cgst', 14, 2)->default(0)->after('total_gst');
                $t->decimal('total_sgst', 14, 2)->default(0)->after('total_cgst');
                $t->decimal('total_igst', 14, 2)->default(0)->after('total_sgst');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->itemTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['cgst_amount', 'sgst_amount', 'igst_amount']);
            });
        }

        foreach ($this->headerTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['total_cgst', 'total_sgst', 'total_igst']);
            });
        }
    }
};
