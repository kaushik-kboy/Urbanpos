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
            $table->string('eway_bill_no', 25)->nullable()->after('status');
            $table->dateTime('eway_bill_date')->nullable()->after('eway_bill_no');
            $table->dateTime('eway_valid_until')->nullable()->after('eway_bill_date');
            $table->string('transporter_id', 50)->nullable()->after('eway_valid_until');
            $table->string('transporter_name', 150)->nullable()->after('transporter_id');
            $table->string('transport_mode', 20)->default('1')->after('transporter_name'); // 1=Road, 2=Rail, 3=Air, 4=Ship
            $table->string('transport_doc_no', 50)->nullable()->after('transport_mode');
            $table->date('transport_doc_date')->nullable()->after('transport_doc_no');
            $table->string('vehicle_no', 30)->nullable()->after('transport_doc_date');
            $table->string('vehicle_type', 10)->default('R')->after('vehicle_no'); // R=Regular, O=ODC
            $table->integer('transport_distance')->nullable()->after('vehicle_type');
            $table->string('eway_status', 20)->default('Pending')->after('transport_distance'); // Pending, Generated, Cancelled
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_bills', function (Blueprint $table) {
            $table->dropColumn([
                'eway_bill_no',
                'eway_bill_date',
                'eway_valid_until',
                'transporter_id',
                'transporter_name',
                'transport_mode',
                'transport_doc_no',
                'transport_doc_date',
                'vehicle_no',
                'vehicle_type',
                'transport_distance',
                'eway_status',
            ]);
        });
    }
};
