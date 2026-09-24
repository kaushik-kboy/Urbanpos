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
            $table->unsignedBigInteger('branch_id')->nullable()->after('document_type');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });

        // Drop the old single-column unique index if it exists
        try {
            Schema::table('receipt_settings', function (Blueprint $table) {
                $table->dropUnique('receipt_settings_document_type_unique');
            });
        } catch (\Throwable $e) {}

        // Add composite index for document_type + branch_id
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->unique(['document_type', 'branch_id'], 'receipt_settings_doc_branch_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->dropUnique('receipt_settings_doc_branch_unique');
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
            $table->unique('document_type', 'receipt_settings_document_type_unique');
        });
    }
};
