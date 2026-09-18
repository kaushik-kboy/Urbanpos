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
        Schema::create('gst_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gstin', 15)->nullable();
            $table->string('username', 100)->nullable();
            $table->string('password', 255)->nullable();
            $table->string('client_id', 150)->nullable();
            $table->string('client_secret', 255)->nullable();
            $table->string('gsp_provider', 50)->default('mock'); // mock, sandbox, masters_india, cleartax, nic_direct
            $table->decimal('auto_upload_threshold', 10, 2)->default(50000.00);
            $table->boolean('auto_upload_enabled')->default(true);
            $table->boolean('is_sandbox')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gst_settings');
    }
};
