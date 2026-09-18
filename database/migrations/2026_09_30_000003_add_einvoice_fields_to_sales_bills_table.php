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
        Schema::table('sales_bills', function (Blueprint $table) {
            $table->string('irn', 64)->nullable()->index()->after('eway_status');
            $table->string('ack_no', 35)->nullable()->after('irn');
            $table->dateTime('ack_date')->nullable()->after('ack_no');
            $table->text('signed_qr_code')->nullable()->after('ack_date');
            $table->longText('signed_invoice')->nullable()->after('signed_qr_code');
            $table->string('einvoice_status', 20)->default('Pending')->index()->after('signed_invoice'); // Pending, Completed, Failed, Cancelled
            $table->text('einvoice_error')->nullable()->after('einvoice_status');
            $table->dateTime('einvoice_synced_at')->nullable()->after('einvoice_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_bills', function (Blueprint $table) {
            $table->dropColumn([
                'irn',
                'ack_no',
                'ack_date',
                'signed_qr_code',
                'signed_invoice',
                'einvoice_status',
                'einvoice_error',
                'einvoice_synced_at',
            ]);
        });
    }
};
