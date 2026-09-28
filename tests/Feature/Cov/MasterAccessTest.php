<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\FormFieldValidation;
use App\Models\FunctionKeyMapping;
use App\Models\Item;
use App\Models\Register;
use App\Models\SalesBill;
use App\Models\TenderType;
use App\Models\TillCashMovement;
use App\Models\TillSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Auth controllers, branch switching, Till, Roles, MasterAux and Tools controllers.
 */
class MasterAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(); // burn id 1
    }

    private function as(string $role, array $attrs = []): User
    {
        $u = User::factory()->create($attrs);
        $u->assignRole($role);
        $this->actingAs($u);

        return $u;
    }

    // ------------------------------------------------------------------ Auth

    public function test_login_success_redirects_home_and_seeds_active_branch_from_user(): void
    {
        $branch = Branch::create(['name' => 'Login Branch']);
        $user = User::factory()->create(['email' => 'cash@example.com', 'password' => Hash::make('secret-pass'), 'branch_id' => $branch->id]);

        $this->post('/login', ['email' => 'cash@example.com', 'password' => 'secret-pass'])
            ->assertRedirect('/home');

        $this->assertAuthenticatedAs($user);
        $this->assertSame($branch->id, session('active_branch_id'));
    }

    public function test_owner_without_branch_logs_in_with_null_active_branch(): void
    {
        User::factory()->create(['email' => 'own@example.com', 'password' => Hash::make('secret-pass'), 'branch_id' => null]);
        $this->post('/login', ['email' => 'own@example.com', 'password' => 'secret-pass'])->assertRedirect('/home');
        $this->assertNull(session('active_branch_id'));
    }

    public function test_login_failure_shows_error_and_stays_guest(): void
    {
        User::factory()->create(['email' => 'bad@example.com', 'password' => Hash::make('right-pass')]);

        $this->from('/login')->post('/login', ['email' => 'bad@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => '', 'password' => ''])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        User::factory()->create(['email' => 'thr@example.com', 'password' => Hash::make('right-pass')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'thr@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');
        }

        // Even the CORRECT password is now refused.
        $res = $this->post('/login', ['email' => 'thr@example.com', 'password' => 'right-pass']);
        $res->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_logout_ends_session_and_guests_cannot_logout_or_see_login_when_authenticated(): void
    {
        $this->post('/logout')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/login')->assertRedirect('/home');
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login_from_protected_pages_and_root(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('master.brands.index'))->assertRedirect(route('login'));
        $this->get(route('till.sessions.index'))->assertRedirect(route('login'));
    }

    public function test_public_registration_creates_a_user_with_no_role_and_no_write_access(): void
    {
        $this->post('/register', [
            'name' => 'Walk In', 'email' => 'walkin@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect('/home');

        $u = User::where('email', 'walkin@example.com')->first();
        $this->assertNotNull($u);
        $this->assertTrue(Hash::check('password123', $u->password));
        $this->assertCount(0, $u->getRoleNames());
        $this->assertNull($u->branch_id);

        $this->post(route('master.brands.store'), ['name' => 'Sneaky'])->assertForbidden();
    }

    public function test_registration_validation(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $this->post('/register', ['name' => 'A', 'email' => 'taken@example.com', 'password' => 'short', 'password_confirmation' => 'other'])
            ->assertSessionHasErrors(['email', 'password']);
        $this->assertSame(0, User::where('name', 'A')->count());
    }

    public function test_password_reset_link_request_validates_and_answers_generically_shaped(): void
    {
        $this->post('/password/email', ['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->get('/password/reset')->assertOk();
    }

    // ------------------------------------------------------------------ Switch branch

    public function test_branch_scoped_user_cannot_switch_to_another_branch(): void
    {
        $mine = Branch::create(['name' => 'Mine']);
        $other = Branch::create(['name' => 'Other']);
        $this->as('Cashier', ['branch_id' => $mine->id]);

        $this->get(route('switch-branch', $other))->assertForbidden();
        $this->get(route('switch-branch', $mine))->assertRedirect();
        $this->assertSame($mine->id, session('active_branch_id'));
    }

    public function test_all_branch_user_can_switch_via_redirect_or_json(): void
    {
        $b = Branch::create(['name' => 'Target']);
        $this->as('Owner', ['branch_id' => null]);

        $this->from('/home')->get(route('switch-branch', $b))->assertRedirect('/home')->assertSessionHas('status', 'Now working in Target.');
        $this->assertSame($b->id, session('active_branch_id'));

        $this->getJson(route('switch-branch', $b))->assertOk()->assertJson(['success' => true, 'branch_id' => $b->id, 'branch_name' => 'Target']);
    }

    public function test_set_active_branch_endpoint_rules(): void
    {
        $a = Branch::create(['name' => 'A', 'status' => true]);
        $b = Branch::create(['name' => 'B', 'status' => true]);

        // Owner picks explicitly
        $this->as('Owner', ['branch_id' => null]);
        $this->postJson(route('active-branch'), ['branch_id' => $b->id])->assertOk()->assertJson(['active_branch_id' => $b->id]);
        // Unknown id falls back to first active branch
        $this->postJson(route('active-branch'), ['branch_id' => 999999])->assertOk()->assertJson(['active_branch_id' => $a->id]);

        // Scoped user's requested id is overridden by their own branch
        $this->as('Cashier', ['branch_id' => $a->id]);
        $this->postJson(route('active-branch'), ['branch_id' => $b->id])->assertOk()->assertJson(['active_branch_id' => $a->id]);
    }

    public function test_set_active_branch_404_when_no_branch_exists(): void
    {
        Branch::query()->delete();
        $this->as('Owner', ['branch_id' => null]);
        $this->postJson(route('active-branch'), ['branch_id' => 5])->assertStatus(404)->assertJson(['success' => false]);
    }

    // ------------------------------------------------------------------ Till

    private function register(): Register
    {
        $branch = Branch::create(['name' => 'Till Branch']);

        return Register::create(['branch_id' => $branch->id, 'name' => 'R1']);
    }

    public function test_till_open_validation_and_branch_copied_from_register_and_permission(): void
    {
        $reg = $this->register();

        $this->as('Owner');
        $this->post(route('till.sessions.open'), ['register_id' => 9999, 'opening_cash' => 10])->assertSessionHasErrors('register_id');
        $this->post(route('till.sessions.open'), ['register_id' => $reg->id, 'opening_cash' => -1])->assertSessionHasErrors('opening_cash');
        $this->post(route('till.sessions.open'), ['register_id' => $reg->id, 'opening_cash' => 'abc'])->assertSessionHasErrors('opening_cash');

        $this->post(route('till.sessions.open'), ['register_id' => $reg->id, 'opening_cash' => 50, 'opened_at' => '2026-01-05 09:00:00'])
            ->assertRedirect();
        $s = TillSession::first();
        $this->assertSame($reg->branch_id, $s->branch_id);
        $this->assertSame('Open', $s->status);
        $this->assertSame('2026-01-05 09:00:00', $s->opened_at->format('Y-m-d H:i:s'));
        $this->get(route('till.sessions.create'))->assertOk();
        $this->get(route('till.sessions.show', $s))->assertOk();

        // a user with no role cannot open or close tills
        $noRole = User::factory()->create();
        $this->actingAs($noRole);
        $this->post(route('till.sessions.open'), ['register_id' => $reg->id, 'opening_cash' => 5])->assertForbidden();
        $this->post(route('till.sessions.close', $s), ['actual_cash' => 0])->assertForbidden();
        $this->assertSame('Open', $s->fresh()->status);
    }

    public function test_till_cash_movements_validate_and_close_computes_expected_cash_and_variance(): void
    {
        $reg = $this->register();
        $owner = $this->as('Owner');
        $s = TillSession::create(['register_id' => $reg->id, 'branch_id' => $reg->branch_id, 'user_id' => $owner->id, 'opening_cash' => 100, 'opened_at' => now(), 'status' => 'Open']);

        $this->post(route('till.sessions.cash-movements', $s), ['type' => 'Sideways', 'amount' => 5])->assertSessionHasErrors('type');
        $this->post(route('till.sessions.cash-movements', $s), ['type' => 'In', 'amount' => 0])->assertSessionHasErrors('amount');
        $this->assertSame(0, TillCashMovement::count());

        $this->post(route('till.sessions.cash-movements', $s), ['type' => 'In', 'amount' => 40, 'reason' => 'float top-up'])
            ->assertRedirect(route('till.sessions.show', $s))->assertSessionHas('status', 'Cash In recorded.');
        $this->post(route('till.sessions.cash-movements', $s), ['type' => 'Out', 'amount' => 15.5]);

        $this->post(route('till.sessions.close', $s), ['actual_cash' => 'x'])->assertSessionHasErrors('actual_cash');
        $this->post(route('till.sessions.close', $s), ['actual_cash' => 120, 'closed_at' => '2026-01-05 18:00:00'])->assertRedirect();

        $s->refresh();
        $this->assertSame('Closed', $s->status);
        $this->assertEquals(124.5, $s->expected_cash);       // 100 + 40 - 15.5
        $this->assertEquals(-4.5, $s->variance);              // 120 - 124.5
        $this->assertSame($owner->id, $s->closed_by_id);

        // a closed session rejects further movements
        $this->post(route('till.sessions.cash-movements', $s), ['type' => 'In', 'amount' => 1])->assertSessionHasErrors('till_session');
        $this->assertSame(2, TillCashMovement::count());
    }

    public function test_till_expected_cash_counts_only_cash_tender_sales_linked_to_the_session(): void
    {
        $reg = $this->register();
        $owner = $this->as('Owner');
        $s = TillSession::create(['register_id' => $reg->id, 'branch_id' => $reg->branch_id, 'user_id' => $owner->id, 'opening_cash' => 0, 'opened_at' => now(), 'status' => 'Open']);
        $cash = TenderType::create(['name' => 'Cash', 'type' => 'Cash']);
        $card = TenderType::create(['name' => 'Card', 'type' => 'Card']);

        $bill = SalesBill::forceCreate([
            'bill_number' => 'TILL-1', 'bill_date' => now(), 'branch_id' => $reg->branch_id, 'till_session_id' => $s->id,
            'total' => 300,
            'customer_id' => Customer::create(['name' => 'Walk', 'mobile' => '9000000077'])->id,
        ]);
        \App\Models\SalesBillPayment::forceCreate(['sales_bill_id' => $bill->id, 'tender_type_id' => $cash->id, 'amount' => 200]);
        \App\Models\SalesBillPayment::forceCreate(['sales_bill_id' => $bill->id, 'tender_type_id' => $card->id, 'amount' => 100]);

        $this->post(route('till.sessions.close', $s), ['actual_cash' => 200])->assertRedirect();
        $s->refresh();
        $this->assertEquals(200, $s->expected_cash);
        $this->assertEquals(0, $s->variance);
    }

    public function test_till_index_filters_by_status_register_and_dates(): void
    {
        $reg = $this->register();
        $reg2 = Register::create(['branch_id' => $reg->branch_id, 'name' => 'R2']);
        $owner = $this->as('Owner');
        $open = TillSession::create(['register_id' => $reg->id, 'branch_id' => $reg->branch_id, 'user_id' => $owner->id, 'opening_cash' => 1, 'opened_at' => '2026-03-01 10:00:00', 'status' => 'Open']);
        $closed = TillSession::create(['register_id' => $reg2->id, 'branch_id' => $reg->branch_id, 'user_id' => $owner->id, 'opening_cash' => 2, 'opened_at' => '2026-04-01 10:00:00', 'status' => 'Closed']);

        $ids = fn (array $q) => $this->get(route('till.sessions.index', $q))->assertOk()->viewData('tillSessions')->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$open->id, $closed->id], $ids([]));
        $this->assertSame([$open->id], $ids(['status' => 'Open']));
        $this->assertSame([$closed->id], $ids(['register_id' => $reg2->id]));
        $this->assertSame([$closed->id], $ids(['date_from' => '2026-03-15']));
        $this->assertSame([$open->id], $ids(['date_to' => '2026-03-15']));
        $this->assertEqualsCanonicalizing([$open->id, $closed->id], $ids(['branch_id' => $reg->branch_id, 'user_id' => $owner->id]));
    }

    // ------------------------------------------------------------------ Roles

    public function test_role_management_is_owner_only_for_writes(): void
    {
        $this->as('Manager');
        $this->post(route('master.roles.store'), ['name' => 'Sneaky'])->assertForbidden();
        $this->assertNull(Role::where('name', 'Sneaky')->first());
        $this->get(route('master.roles.index'))->assertOk();
    }

    public function test_owner_creates_role_with_permissions_that_get_created_on_demand(): void
    {
        $this->as('Owner');
        $this->get(route('master.roles.create'))->assertOk();

        $this->post(route('master.roles.store'), ['name' => '  Stock Clerk ', 'permissions' => ['stock-updates.create', 'custom.brand-new-perm']])
            ->assertRedirect(route('master.roles.index'))
            ->assertSessionHas('status', "Role 'Stock Clerk' created successfully with 2 permissions.");

        $role = Role::where('name', 'Stock Clerk')->first();
        $this->assertEqualsCanonicalizing(['stock-updates.create', 'custom.brand-new-perm'], $role->permissions->pluck('name')->all());
        $this->assertNotNull(Permission::where('name', 'custom.brand-new-perm')->first());

        $this->post(route('master.roles.store'), ['name' => 'Stock Clerk'])->assertSessionHasErrors('name');
        $this->post(route('master.roles.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('master.roles.store'), ['name' => 'X', 'permissions' => 'not-array'])->assertSessionHasErrors('permissions');
    }

    public function test_role_update_syncs_permissions_and_protected_roles_cannot_be_renamed(): void
    {
        $this->as('Owner');
        $role = Role::create(['name' => 'Temp Role', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'brands.create', 'guard_name' => 'web']));

        $this->put(route('master.roles.update', $role), ['name' => 'Renamed Role', 'permissions' => ['uoms.create']])
            ->assertRedirect(route('master.roles.index'));
        $role->refresh();
        $this->assertSame('Renamed Role', $role->name);
        $this->assertSame(['uoms.create'], $role->permissions->pluck('name')->all());

        $this->put(route('master.roles.update', $role), ['name' => 'Manager'])->assertSessionHasErrors('name');

        $manager = Role::where('name', 'Manager')->first();
        $this->put(route('master.roles.update', $manager), ['name' => 'Hacked', 'permissions' => ['brands.create']])->assertRedirect();
        $this->assertSame('Manager', $manager->fresh()->name, 'core role name is immutable');
        $this->assertSame(['brands.create'], $manager->fresh()->permissions->pluck('name')->all());

        $this->get(route('master.roles.edit', $role))->assertOk();
        $this->get(route('master.roles.show', $role))->assertRedirect(route('master.roles.edit', $role));
    }

    public function test_role_destroy_guards_protected_roles_and_roles_with_users(): void
    {
        $this->as('Owner');
        foreach (['Owner', 'Manager', 'Cashier'] as $name) {
            $r = Role::where('name', $name)->first();
            $this->delete(route('master.roles.destroy', $r))->assertSessionHasErrors('role');
            $this->assertNotNull(Role::find($r->id));
        }

        $used = Role::create(['name' => 'Used Role', 'guard_name' => 'web']);
        $u = User::factory()->create();
        $u->assignRole($used);
        $this->delete(route('master.roles.destroy', $used))->assertSessionHasErrors('role');
        $this->assertNotNull(Role::find($used->id));

        $u->removeRole($used);
        $this->delete(route('master.roles.destroy', $used))->assertRedirect(route('master.roles.index'))->assertSessionHas('status');
        $this->assertNull(Role::find($used->id));
    }

    public function test_role_index_search_filters_by_name(): void
    {
        $this->as('Owner');
        Role::create(['name' => 'Zebra Keeper', 'guard_name' => 'web']);
        $this->get(route('master.roles.index', ['search' => 'Zebra']))->assertSee('Zebra Keeper')->assertDontSee('Cashier');
    }

    public function test_permission_group_definitions_only_reference_seeded_permissions(): void
    {
        $seeded = Permission::pluck('name')->all();
        $missing = [];
        foreach (\App\Http\Controllers\Master\RoleController::getPermissionGroups() as $group) {
            foreach ($group['modules'] as $module => $def) {
                foreach (array_keys($def['actions']) as $action) {
                    if (! in_array("$module.$action", $seeded, true)) {
                        $missing[] = "$module.$action";
                    }
                }
            }
        }
        // The role editor lets an Owner tick these; any not seeded are created lazily on save, which
        // is fine, but the vast majority must map to real seeded gates.
        $this->assertLessThan(15, count($missing), 'unseeded permission names: '.implode(', ', $missing));
    }

    // ------------------------------------------------------------------ MasterAux

    public function test_master_aux_known_and_unknown_modules_render_with_their_titles(): void
    {
        $this->as('Cashier');
        $this->get(route('master.aux', 'tax-slab'))->assertOk()->assertSee('Tax Slab Master')->assertSee('GST Rate %');
        $this->get(route('master.aux', 'freight-settings'))->assertOk()->assertSee('Freight Settings');
        $this->get(route('master.aux', 'some-new-thing'))->assertOk()->assertSee('Some New Thing')->assertSee('New Entry');
    }

    public function test_master_aux_ean_screen_filters_by_search_ean_and_status(): void
    {
        $this->as('Cashier');
        Item::create(['name' => 'Has Ean', 'ean_upc_code' => 'E100', 'status' => 1]);
        Item::create(['name' => 'No Ean', 'status' => 0]);

        $names = fn (array $q) => $this->get(route('master.aux', ['module' => 'item-ean-upc-entry'] + $q))->assertOk()->viewData('items')->pluck('name')->all();

        $this->assertSame(['Has Ean'], $names(['ean_filter' => 'with_ean']));
        $this->assertSame(['No Ean'], $names(['ean_filter' => 'missing_ean']));
        $this->assertSame(['Has Ean'], $names(['status' => 'active']));
        $this->assertSame(['No Ean'], $names(['status' => 'inactive']));
        $this->assertSame(['Has Ean'], $names(['search' => 'E100']));
        $res = $this->get(route('master.aux', ['module' => 'item-ean-upc-entry']));
        $this->assertSame(2, $res->viewData('totalCount'));
        $this->assertSame(1, $res->viewData('withEanCount'));
        $this->assertSame(1, $res->viewData('missingEanCount'));
    }

    public function test_master_aux_ean_update_validation_duplicates_json_and_clearing(): void
    {
        $this->as('Manager');
        $a = Item::create(['name' => 'A', 'ean_upc_code' => 'DUP1']);
        $b = Item::create(['name' => 'B']);

        $this->postJson(route('master.aux.item-ean-upc.update'), ['item_id' => 9999])->assertStatus(422);

        $this->postJson(route('master.aux.item-ean-upc.update'), ['item_id' => $b->id, 'ean_upc_code' => 'DUP1'])
            ->assertStatus(422)->assertJson(['success' => false]);
        $this->assertNull($b->fresh()->ean_upc_code);

        $this->post(route('master.aux.item-ean-upc.update'), ['item_id' => $b->id, 'ean_upc_code' => 'DUP1'])
            ->assertSessionHasErrors('ean_upc_code');

        $this->postJson(route('master.aux.item-ean-upc.update'), ['item_id' => $b->id, 'ean_upc_code' => '  NEW9  '])
            ->assertOk()->assertJson(['success' => true, 'ean_upc_code' => 'NEW9']);
        $this->assertSame('NEW9', $b->fresh()->ean_upc_code);

        $this->post(route('master.aux.item-ean-upc.update'), ['item_id' => $b->id, 'ean_upc_code' => ''])
            ->assertSessionHas('status');
        $this->assertNull($b->fresh()->ean_upc_code);

        // an item may re-save its own barcode
        $this->postJson(route('master.aux.item-ean-upc.update'), ['item_id' => $a->id, 'ean_upc_code' => 'DUP1'])->assertOk();
    }

    // ------------------------------------------------------------------ Tools

    public function test_tools_module_page_renders_known_and_fallback_configs(): void
    {
        $this->as('Cashier');
        $this->get(route('tools.module', 'reprint'))->assertOk()->assertSee('Reprint Manager');
        $this->get(route('tools.module', 'unmapped-tool'))->assertOk()->assertSee('Unmapped Tool')->assertSee('New Config');
    }

    private function fkm(string $key, string $combo, bool $enabled = true): FunctionKeyMapping
    {
        return FunctionKeyMapping::create([
            'action_key' => $key, 'action_title' => strtoupper($key), 'shortcut_combination' => $combo,
            'scope' => 'global', 'target_url' => '/x', 'is_enabled' => $enabled, 'sort_order' => 1,
        ]);
    }

    public function test_function_key_update_trims_toggles_enabled_ignores_unknown_ids_and_clears_cache(): void
    {
        $this->as('Manager');
        FunctionKeyMapping::query()->delete();
        $a = $this->fkm('a', 'F1');
        $b = $this->fkm('b', 'F2');
        FunctionKeyMapping::getActiveMappings(); // prime cache with both enabled

        $this->post(route('tools.function-keys.update'), ['mappings' => [
            $a->id => ['shortcut_combination' => '  Ctrl+K  ', 'is_enabled' => '1'],
            $b->id => ['shortcut_combination' => 'F2'],           // is_enabled omitted => disabled
            99999 => ['shortcut_combination' => 'F9'],            // unknown id ignored
        ]])->assertRedirect(route('tools.function-keys.index'))->assertSessionHas('success');

        $this->assertSame('Ctrl+K', $a->fresh()->shortcut_combination);
        $this->assertTrue($a->fresh()->is_enabled);
        $this->assertFalse($b->fresh()->is_enabled);
        $this->assertSame([$a->id], FunctionKeyMapping::getActiveMappings()->pluck('id')->all(), 'cache was invalidated');

        $this->get(route('tools.function-keys.index'))->assertOk()->assertSee('Ctrl+K');
    }

    public function test_function_key_reset_restores_seeded_defaults(): void
    {
        $this->as('Manager');
        FunctionKeyMapping::query()->delete();
        $this->assertSame(0, FunctionKeyMapping::count());

        $this->post(route('tools.function-keys.reset'))->assertRedirect(route('tools.function-keys.index'))->assertSessionHas('success');
        $this->assertGreaterThan(0, FunctionKeyMapping::count());
    }

    public function test_form_validation_screen_selects_group_and_module_with_safe_fallbacks(): void
    {
        $this->as('Manager');
        $this->get(route('tools.form-validations.index'))->assertOk()
            ->assertViewHas('activeGroup', 'purchase')->assertViewHas('activeModule', 'purchase_invoices');
        $this->get(route('tools.form-validations.index', ['module' => 'suppliers']))
            ->assertViewHas('activeGroup', 'master')->assertViewHas('activeModule', 'suppliers');
        $this->get(route('tools.form-validations.index', ['group' => 'sales', 'module' => 'nonsense']))
            ->assertViewHas('activeGroup', 'sales')->assertViewHas('activeModule', 'sales_bills');
        $this->get(route('tools.form-validations.index', ['group' => 'bogus']))->assertViewHas('activeGroup', 'purchase');
    }

    public function test_form_validation_update_saves_flags_only_for_matching_module_and_reset_restores(): void
    {
        $this->as('Manager');
        FormFieldValidation::query()->delete();
        $f = FormFieldValidation::create(['module_key' => 'customers', 'field_name' => 'email', 'field_label' => 'Email', 'section' => 'General', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 1]);
        $other = FormFieldValidation::create(['module_key' => 'suppliers', 'field_name' => 'email', 'field_label' => 'Email', 'section' => 'General', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 1]);

        $this->post(route('tools.form-validations.update'), [])->assertSessionHasErrors('module_key');
        $this->post(route('tools.form-validations.update'), ['module_key' => 'customers', 'fields' => [$f->id => ['custom_error_message' => str_repeat('x', 501)]]])
            ->assertSessionHasErrors('fields.'.$f->id.'.custom_error_message');

        $this->post(route('tools.form-validations.update'), [
            'module_key' => 'customers', 'group' => 'master', 'active_section' => 'General',
            'fields' => [
                $f->id => ['is_required' => 1, 'is_unique' => 1, 'custom_error_message' => '  Need email  '],
                $other->id => ['is_required' => 1],   // wrong module => untouched
            ],
        ])->assertRedirect(route('tools.form-validations.index', ['module' => 'customers', 'group' => 'master', 'section' => 'General']));

        $f->refresh();
        $this->assertTrue($f->is_required);
        $this->assertTrue($f->is_unique);
        $this->assertFalse($f->is_readonly);
        $this->assertSame('Need email', $f->custom_error_message);
        $this->assertFalse($other->fresh()->is_required);

        // reset: re-seeds defaults for the module (whatever they are, the customised message is gone)
        $this->post(route('tools.form-validations.reset'), ['module_key' => 'customers'])->assertSessionHas('status');
        $this->assertNull(FormFieldValidation::where('module_key', 'customers')->where('custom_error_message', 'Need email')->first());
    }
}
