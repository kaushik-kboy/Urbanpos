<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expiry_bucket_settings', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->integer('min_days');
            $table->integer('max_days');
            $table->string('severity');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed the 3 default buckets per URBANPETS_DATABASE_AND_WORKFLOW.md §3.
        DB::table('expiry_bucket_settings')->insert([
            ['label' => 'Expiring Soon', 'min_days' => 0, 'max_days' => 30, 'severity' => 'critical', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Expiring Later', 'min_days' => 31, 'max_days' => 60, 'severity' => 'warning', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Watch List', 'min_days' => 61, 'max_days' => 120, 'severity' => 'info', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('expiry_bucket_settings');
    }
};
