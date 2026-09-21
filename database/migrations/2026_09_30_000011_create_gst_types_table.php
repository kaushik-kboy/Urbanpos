<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable();
            $table->string('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // Seed standard GST types
        $defaultGstTypes = [
            ['name' => 'Regular', 'code' => 'REG', 'description' => 'Regular GST registered taxpayer', 'status' => true],
            ['name' => 'Composite', 'code' => 'CMP', 'description' => 'Composition scheme taxpayer', 'status' => true],
            ['name' => 'Un Register', 'code' => 'URG', 'description' => 'Unregistered dealer / person', 'status' => true],
            ['name' => 'Overseas', 'code' => 'OVS', 'description' => 'Export / SEZ / Overseas dealer', 'status' => true],
            ['name' => 'Consumer', 'code' => 'CON', 'description' => 'End consumer', 'status' => true],
        ];

        foreach ($defaultGstTypes as $type) {
            DB::table('gst_types')->insertOrIgnore(array_merge($type, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_types');
    }
};
