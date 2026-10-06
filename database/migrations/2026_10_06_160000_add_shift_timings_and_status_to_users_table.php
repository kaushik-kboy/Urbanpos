<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('branch_id');
            }
            if (!Schema::hasColumn('users', 'time_in')) {
                $table->time('time_in')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('users', 'time_out')) {
                $table->time('time_out')->nullable()->after('time_in');
            }
        });

        // Ensure Cashier role is strictly scoped to retail counter permissions
        $cashier = Role::where('name', 'Cashier')->first();
        if ($cashier) {
            $cashier->syncPermissions([
                'sales-bills.create',
                'sales-returns.create',
                'till.open',
                'till.close',
                'customers.create',
            ]);
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('users', 'time_out')) {
                $columns[] = 'time_out';
            }
            if (Schema::hasColumn('users', 'time_in')) {
                $columns[] = 'time_in';
            }
            if (Schema::hasColumn('users', 'is_active')) {
                $columns[] = 'is_active';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
