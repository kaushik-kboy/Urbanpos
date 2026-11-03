<?php

namespace Database\Seeders;

use App\Models\ReplenishmentSalesRule;
use App\Models\ReplenishmentStockRule;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ReplenishmentRulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Sales Movement Rules (5 Static Rules as per V1 Spec)
        $salesRules = [
            [
                'rule_name' => 'Highest Moving',
                'min_daily_avg' => 20.00,
                'max_daily_avg' => null,
                'target_cover_days' => 30,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'rule_name' => 'Fast Moving',
                'min_daily_avg' => 10.00,
                'max_daily_avg' => 19.99,
                'target_cover_days' => 25,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'rule_name' => 'Normal Moving',
                'min_daily_avg' => 5.00,
                'max_daily_avg' => 9.99,
                'target_cover_days' => 20,
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'rule_name' => 'Slow Moving',
                'min_daily_avg' => 1.00,
                'max_daily_avg' => 4.99,
                'target_cover_days' => 15,
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'rule_name' => 'Dead Stock',
                'min_daily_avg' => 0.00,
                'max_daily_avg' => 0.99,
                'target_cover_days' => 7,
                'sort_order' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($salesRules as $rule) {
            ReplenishmentSalesRule::updateOrCreate(
                ['rule_name' => $rule['rule_name']],
                $rule
            );
        }

        // 2. Stock Condition Rules (4 Stock Types as per V1 Spec)
        $stockRules = [
            [
                'stock_type' => 'Critical',
                'min_cover_days' => 0,
                'max_cover_days' => 3,
                'color_code' => 'danger', // Red
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'stock_type' => 'Low',
                'min_cover_days' => 4,
                'max_cover_days' => 10,
                'color_code' => 'warning', // Yellow/Amber
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'stock_type' => 'Healthy',
                'min_cover_days' => 11,
                'max_cover_days' => 30,
                'color_code' => 'success', // Green
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'stock_type' => 'Overstock',
                'min_cover_days' => 31,
                'max_cover_days' => null,
                'color_code' => 'info', // Blue
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($stockRules as $rule) {
            ReplenishmentStockRule::updateOrCreate(
                ['stock_type' => $rule['stock_type']],
                $rule
            );
        }

        // 3. Permission & Roles Provisioning
        if (class_exists(Permission::class)) {
            $permission = Permission::firstOrCreate(['name' => 'smart-replenishment.view', 'guard_name' => 'web']);
            foreach (Role::whereIn('name', ['Super Admin', 'Admin', 'Owner', 'Manager'])->get() as $role) {
                $role->givePermissionTo($permission);
            }
        }
    }
}
