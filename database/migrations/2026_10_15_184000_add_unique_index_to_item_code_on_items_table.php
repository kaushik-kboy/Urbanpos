<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add database-level UNIQUE constraint on items.item_code
        // This physically prevents simultaneous race-condition duplicates across multiple computers.
        Schema::table('items', function (Blueprint $table) {
            try {
                $table->dropIndex(['item_code']);
            } catch (\Throwable) {}

            $table->unique('item_code');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(['item_code']);
            $table->index('item_code');
        });
    }
};
