<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * stock_updates already has a status enum (Pending/Approved/Rejected, default Approved)
     * from 2026_09_11_000001 which serves as its posting-lifecycle gate (Approved == Posted).
     * We do not add a second status column there — only posting_key, for idempotency parity
     * with the other five transaction headers.
     */
    private array $tablesWithNewStatus = [
        'purchase_invoices',
        'sales_bills',
        'sales_returns',
        'damage_stocks',
        'opening_stocks',
    ];

    public function up(): void
    {
        foreach ($this->tablesWithNewStatus as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->enum('status', ['Draft', 'Posted', 'Cancelled'])->default('Posted');
                $table->string('posting_key')->nullable()->unique();
            });
        }

        Schema::table('stock_updates', function (Blueprint $table) {
            $table->string('posting_key')->nullable()->unique();
        });
    }

    public function down(): void
    {
        foreach ($this->tablesWithNewStatus as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['status', 'posting_key']);
            });
        }

        Schema::table('stock_updates', function (Blueprint $table) {
            $table->dropColumn('posting_key');
        });
    }
};
