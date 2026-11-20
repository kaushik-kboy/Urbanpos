<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_addresses')) {
            Schema::create('customer_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('address_type', 50)->default('Home'); // Home, Office, Clinic, Farmhouse, Other
                $table->string('address_line')->nullable();
                $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
                $table->string('area_name')->nullable();
                $table->string('city')->default('Ahmedabad');
                $table->string('state')->default('Gujarat');
                $table->string('country')->default('India');
                $table->string('postal_code', 20)->nullable();
                $table->string('landmark')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->index(['customer_id', 'is_primary']);
            });
        }

        if (Schema::hasTable('sales_bills') && ! Schema::hasColumn('sales_bills', 'customer_address_id')) {
            Schema::table('sales_bills', function (Blueprint $table) {
                $table->foreignId('customer_address_id')->nullable()->after('customer_id')->constrained('customer_addresses')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_bills') && Schema::hasColumn('sales_bills', 'customer_address_id')) {
            Schema::table('sales_bills', function (Blueprint $table) {
                $table->dropForeign(['customer_address_id']);
                $table->dropColumn('customer_address_id');
            });
        }

        Schema::dropIfExists('customer_addresses');
    }
};
