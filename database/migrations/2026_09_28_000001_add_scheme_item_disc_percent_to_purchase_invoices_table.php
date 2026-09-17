<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_invoices', 'scheme_item_disc_percent')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->decimal('scheme_item_disc_percent', 5, 2)->default(0)->after('scheme_item_disc_amt');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_invoices', 'scheme_item_disc_percent')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->dropColumn('scheme_item_disc_percent');
            });
        }
    }
};
