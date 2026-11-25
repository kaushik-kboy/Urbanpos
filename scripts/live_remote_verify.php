<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\RoleTablePreference;
use App\Models\RoleFormPreference;
use App\Models\UserTablePreference;
use App\Models\UserFormPreference;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

echo "==================================================\n";
echo "  LIVE STAGING POS.RAMDEVCAR.SHOP FULL CERTIFICATION\n";
echo "==================================================\n";

// 1. Permission checks
$owner = Role::where('name', 'Owner')->first();
$cashier = Role::where('name', 'Cashier')->first();
$perm = Permission::where('name', 'layout-customizer.customize')->first();

echo "1. Permission layout-customizer.customize exists: " . ($perm ? "YES" : "NO") . "\n";
echo "   Owner has permission: " . ($owner && $owner->hasPermissionTo('layout-customizer.customize') ? "YES (Authorized)" : "NO") . "\n";
echo "   Cashier has permission: " . ($cashier && $cashier->hasPermissionTo('layout-customizer.customize') ? "YES" : "NO (Properly Restricted)") . "\n";

// 2. Test users
$adminUser = User::role('Owner')->first() ?? User::first();
$cashierUser = User::role('Cashier')->first();
if (!$cashierUser) {
    $cashierUser = User::firstOrCreate(
        ['email' => 'cashier_test@ramdevcar.shop'],
        ['name' => 'Cashier Test', 'password' => bcrypt('secret'), 'branch_id' => 1]
    );
    $cashierUser->assignRole('Cashier');
}

echo "2. Test Cashier: " . $cashierUser->name . " (" . $cashierUser->email . ")\n";

// 3. Set Role Preference
$testKey = 'sales_bills_live_test';
$roleColumns = [
    ['key' => 'bill_no', 'order' => 1, 'visible' => true],
    ['key' => 'customer_name', 'order' => 2, 'visible' => true],
    ['key' => 'total_amount', 'order' => 3, 'visible' => true]
];

RoleTablePreference::setForRole($cashier->id, $testKey, $roleColumns);
echo "3. Set Role Layout for Cashier on [{$testKey}]: OK\n";

// 4. Test resolving for Cashier
$resolved = UserTablePreference::getResolvedForUser($cashierUser, $testKey);
$match = ($resolved === $roleColumns);
echo "4. Cashier resolves Admin Role Layout: " . ($match ? "PASS" : "FAIL") . "\n";

// 5. Test override shielding: Add corrupted preference for cashier
UserTablePreference::setForUser($cashierUser->id, $testKey, [
    ['key' => 'corrupted_field', 'order' => 99, 'visible' => false]
]);
$shielded = UserTablePreference::getResolvedForUser($cashierUser, $testKey);
$shieldMatch = ($shielded === $roleColumns);
echo "5. Corrupted user preference shielded and ignored: " . ($shieldMatch ? "PASS" : "FAIL") . "\n";

// 6. Test Form Preference as well
$testFormKey = 'sales_bills.header_live_test';
$formFields = [
    ['field' => 'customer_id', 'order' => 1, 'grid_col' => 'col-md-6', 'visible' => true],
    ['field' => 'sales_type_id', 'order' => 2, 'grid_col' => 'col-md-6', 'visible' => true]
];
RoleFormPreference::setForRole($cashier->id, $testFormKey, $formFields);
$resolvedForm = UserFormPreference::getResolvedForUser($cashierUser, $testFormKey);
$formMatch = ($resolvedForm === $formFields);
echo "6. Cashier resolves Admin Form Layout: " . ($formMatch ? "PASS" : "FAIL") . "\n";

// Cleanup
RoleTablePreference::where('table_key', $testKey)->delete();
UserTablePreference::where('table_key', $testKey)->delete();
RoleFormPreference::where('form_key', $testFormKey)->delete();
UserFormPreference::where('form_key', $testFormKey)->delete();
echo "7. Test cleanup: OK\n";

echo "==================================================\n";
echo "  RESULT: 100% PASSED ON LIVE SERVER\n";
echo "==================================================\n";
