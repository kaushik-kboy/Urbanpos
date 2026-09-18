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
        Schema::create('system_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50)->default('General')->index();
            $table->string('error_type', 100)->nullable()->index();
            $table->text('message');
            $table->string('file', 500)->nullable();
            $table->integer('line')->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('method', 10)->default('GET');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 150)->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->longText('request_data')->nullable();
            $table->longText('stack_trace')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->enum('status', ['Unresolved', 'Resolved', 'Ignored'])->default('Unresolved')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->index(['module', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_error_logs');
    }
};
