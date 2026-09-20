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
        Schema::table('document_sequences', function (Blueprint $table) {
            $table->string('document_type', 50)->nullable()->after('id')->index();
            $table->string('document_title', 100)->nullable()->after('document_type');
            $table->string('prefix', 50)->nullable()->default('SB-{YEAR}-')->after('document_title');
            $table->string('suffix', 50)->nullable()->after('prefix');
            $table->unsignedInteger('padding_zeros')->default(4)->after('suffix');
            $table->unsignedBigInteger('starting_number')->default(1)->after('padding_zeros');
            $table->string('reset_frequency', 30)->default('financial_year')->after('starting_number'); // 'financial_year', 'yearly', 'monthly', 'never'
            $table->string('last_reset_period', 50)->nullable()->after('reset_frequency'); // e.g. '2026-2027', '2026', '2026-09'
            $table->unsignedBigInteger('branch_id')->nullable()->after('last_reset_period')->index();
            $table->boolean('is_active')->default(true)->after('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_sequences', function (Blueprint $table) {
            $table->dropColumn([
                'document_type',
                'document_title',
                'prefix',
                'suffix',
                'padding_zeros',
                'starting_number',
                'reset_frequency',
                'last_reset_period',
                'branch_id',
                'is_active',
            ]);
        });
    }
};
