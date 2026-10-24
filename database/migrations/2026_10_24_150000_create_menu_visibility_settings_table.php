<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menu_visibility_settings')) {
            Schema::create('menu_visibility_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('role_id')->nullable()->index();
                $table->string('menu_key', 191);
                $table->boolean('is_visible')->default(true);
                $table->timestamps();

                $table->unique(['role_id', 'menu_key'], 'role_menu_unique');
                
                // Foreign key constraint if roles table exists
                if (Schema::hasTable('roles')) {
                    $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_visibility_settings');
    }
};
