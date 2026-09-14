<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_bills', function (Blueprint $table) {
            $table->foreignId('till_session_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('till_session_id');
        });
    }
};
