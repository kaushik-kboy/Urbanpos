<?php

namespace Tests\Feature\Cov2;

use App\Models\Breed;
use App\Models\PetType;
use App\Models\Uom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MasterBLockImportTest extends TestCase
{
    use MasterBHelper, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->burnSuperUser();
    }

    // ------------------------------------------------------------ PosLockController

    public function test_guest_gets_redirected_from_pin_endpoints(): void
    {
        $this->post(route('pos.verify-pin'), ['pin' => '0000'])->assertRedirect(route('login'));
        $this->post(route('pos.update-pin'), ['new_pin' => '1234'])->assertRedirect(route('login'));
    }

    public function test_first_unlock_with_default_0000_saves_a_hashed_pin(): void
    {
        $u = $this->actAs('Cashier');
        $this->assertNull($u->fresh()->pos_pin);

        $this->postJson(route('pos.verify-pin'), ['pin' => '0000'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('user.id', $u->id)->assertJsonPath('user.name', $u->name);

        $stored = $u->fresh()->pos_pin;
        $this->assertNotSame('0000', $stored);
        $this->assertTrue(Hash::check('0000', $stored));
    }

    public function test_wrong_pin_before_any_pin_is_set_is_rejected_and_nothing_is_saved(): void
    {
        $u = $this->actAs('Cashier');
        $this->postJson(route('pos.verify-pin'), ['pin' => '1234'])
            ->assertStatus(422)->assertJsonPath('success', false)->assertJsonPath('message', 'Invalid PIN. 4 attempts remaining.');
        $this->assertNull($u->fresh()->pos_pin);
        RateLimiter::clear('pos-lock-pin:'.$u->id);
    }

    public function test_pin_format_validation(): void
    {
        $u = $this->actAs('Cashier');
        foreach (['', '123', '12345', 'abcd', '12a4'] as $bad) {
            $this->postJson(route('pos.verify-pin'), ['pin' => $bad])->assertStatus(422)->assertJsonValidationErrors('pin');
        }
        $this->postJson(route('pos.verify-pin'), [])->assertStatus(422)->assertJsonValidationErrors('pin');
        RateLimiter::clear('pos-lock-pin:'.$u->id);
    }

    public function test_configured_pin_unlocks_wrong_pin_counts_down_and_success_resets_counter(): void
    {
        $u = $this->actAs('Cashier', ['pos_pin' => Hash::make('4321')]);
        $key = 'pos-lock-pin:'.$u->id;
        RateLimiter::clear($key);

        $this->postJson(route('pos.verify-pin'), ['pin' => '0000'])
            ->assertStatus(422)->assertJsonPath('message', 'Invalid PIN. 4 attempts remaining.');
        $this->postJson(route('pos.verify-pin'), ['pin' => '1111'])
            ->assertStatus(422)->assertJsonPath('message', 'Invalid PIN. 3 attempts remaining.');
        // the default 0000 no longer works once a PIN is configured
        $this->assertFalse(Hash::check('0000', $u->fresh()->pos_pin));

        $this->postJson(route('pos.verify-pin'), ['pin' => '4321'])->assertOk()->assertJsonPath('success', true);
        $this->assertSame(0, RateLimiter::attempts($key), 'a correct PIN clears the failure counter');
    }

    public function test_lockout_after_five_wrong_pins_blocks_even_the_correct_pin_and_is_per_user(): void
    {
        $u = $this->actAs('Cashier', ['pos_pin' => Hash::make('4321')]);
        RateLimiter::clear('pos-lock-pin:'.$u->id);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('pos.verify-pin'), ['pin' => '9999'])->assertStatus(422);
        }

        $res = $this->postJson(route('pos.verify-pin'), ['pin' => '4321'])->assertStatus(429)->assertJsonPath('success', false);
        $this->assertMatchesRegularExpression('/^Too many incorrect attempts\. Please wait \d+ seconds before trying again\.$/', $res->json('message'));

        // another user is unaffected
        $other = $this->actAs('Cashier', ['pos_pin' => Hash::make('1357')]);
        $this->postJson(route('pos.verify-pin'), ['pin' => '1357'])->assertOk();
        RateLimiter::clear('pos-lock-pin:'.$u->id);
        RateLimiter::clear('pos-lock-pin:'.$other->id);
    }

    public function test_update_pin_validation_and_first_time_set(): void
    {
        $u = $this->actAs('Cashier');

        $this->postJson(route('pos.update-pin'), [])->assertStatus(422)->assertJsonValidationErrors('new_pin');
        $this->postJson(route('pos.update-pin'), ['new_pin' => '12'])->assertStatus(422)->assertJsonValidationErrors('new_pin');
        $this->postJson(route('pos.update-pin'), ['new_pin' => '1234', 'current_pin' => 'abcd'])->assertStatus(422)->assertJsonValidationErrors('current_pin');
        $this->assertNull($u->fresh()->pos_pin);

        $this->postJson(route('pos.update-pin'), ['new_pin' => '2468'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('message', 'POS PIN updated successfully.');
        $this->assertTrue(Hash::check('2468', $u->fresh()->pos_pin));
    }

    public function test_update_pin_requires_correct_current_pin_when_supplied(): void
    {
        $u = $this->actAs('Cashier', ['pos_pin' => Hash::make('1111')]);

        $this->postJson(route('pos.update-pin'), ['current_pin' => '2222', 'new_pin' => '3333'])
            ->assertStatus(422)->assertJsonPath('success', false)->assertJsonPath('message', 'Current PIN is incorrect.');
        $this->assertTrue(Hash::check('1111', $u->fresh()->pos_pin));

        $this->postJson(route('pos.update-pin'), ['current_pin' => '1111', 'new_pin' => '3333'])->assertOk();
        $this->assertTrue(Hash::check('3333', $u->fresh()->pos_pin));
        $this->assertFalse(Hash::check('1111', $u->fresh()->pos_pin));
    }

    public function test_update_pin_with_existing_pin_must_not_allow_change_without_current_pin(): void
    {
        // Regression: an already-configured PIN could be overwritten by omitting current_pin,
        // which defeats the lock screen for anyone at an unattended session.
        $u = $this->actAs('Cashier', ['pos_pin' => Hash::make('1111')]);

        $this->postJson(route('pos.update-pin'), ['new_pin' => '5555'])
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->assertTrue(Hash::check('1111', $u->fresh()->pos_pin));
    }

    // ------------------------------------------------------------ Importable leftovers

    private function csv(string $content, string $name = 'in.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function importRes($response): array
    {
        return $response->baseResponse->getSession()->get('import_result');
    }

    public function test_import_empty_file_reports_no_data_rows_and_creates_nothing(): void
    {
        $this->actAs('Owner');
        $before = Uom::count();

        $r = $this->importRes($this->post(route('master.uoms.import'), ['file' => $this->csv('')])->assertRedirect());

        $this->assertSame(['The file has no data rows.'], $r['errors']);
        $this->assertSame(1, $r['error_count']);
        $this->assertSame(0, $r['created']);
        $this->assertSame($before, Uom::count());
    }

    public function test_import_header_only_file_creates_nothing(): void
    {
        $this->actAs('Owner');
        $before = Uom::count();
        $r = $this->importRes($this->post(route('master.uoms.import'), ['file' => $this->csv("Name,Alias\n")]));
        $this->assertSame(0, $r['created'] + $r['updated'] + $r['skipped']);
        $this->assertSame($before, Uom::count());
    }

    public function test_import_without_recognisable_headers_skips_every_row_with_required_error(): void
    {
        $this->actAs('Owner');
        $before = Uom::count();
        $r = $this->importRes($this->post(route('master.uoms.import'), ['file' => $this->csv("foo,bar\nx,y\nz,w\n")]));

        $this->assertSame(2, $r['skipped']);
        $this->assertSame(2, $r['error_count']);
        $this->assertSame('Row 2: Missing required value for "Name".', $r['errors'][0]);
        $this->assertSame('Row 3: Missing required value for "Name".', $r['errors'][1]);
        $this->assertSame($before, Uom::count());
    }

    public function test_import_accepts_global_header_aliases_and_upserts_on_unique_key(): void
    {
        $this->actAs('Owner');
        Uom::create(['name' => 'Box', 'alias' => 'OLD', 'status' => 1]);
        // "Item name" is a global alias of "Name"
        $r = $this->importRes($this->post(route('master.uoms.import'), ['file' => $this->csv("Item name,Alias\nBox,BX\nCarton,CT\n")]));
        $this->assertSame(1, $r['updated']);
        $this->assertSame(1, $r['created']);
        $this->assertSame('BX', Uom::where('name', 'Box')->value('alias'));
        $this->assertSame(1, Uom::where('name', 'Box')->count());
    }

    public function test_import_relation_resolver_exception_becomes_a_row_error_and_import_continues(): void
    {
        $this->actAs('Owner');
        $tooLong = str_repeat('P', 300); // pet_types.name is varchar(255): resolver's firstOrCreate throws
        $csv = "Name,Pet Type\nBadBreed,$tooLong\nGoodBreed,Rabbit\n";

        $r = $this->importRes($this->post(route('master.breeds.import'), ['file' => $this->csv($csv)]));

        $this->assertSame(1, $r['created']);
        $this->assertSame(1, $r['skipped']);
        $this->assertStringStartsWith('Row 2:', $r['errors'][0]);
        $this->assertNull(Breed::where('name', 'BadBreed')->first());
        $this->assertSame(PetType::where('name', 'Rabbit')->value('id'), Breed::where('name', 'GoodBreed')->value('pet_type_id'));
    }

    public function test_import_caps_reported_errors_at_twenty_but_counts_all(): void
    {
        $this->actAs('Owner');
        $rows = "Name,Alias\n";
        for ($i = 0; $i < 25; $i++) {
            $rows .= ",A$i\n"; // missing required Name
        }
        $r = $this->importRes($this->post(route('master.uoms.import'), ['file' => $this->csv($rows)]));

        $this->assertSame(25, $r['error_count']);
        $this->assertCount(20, $r['errors']);
        $this->assertSame(25, $r['skipped']);
    }

    public function test_import_sample_is_a_header_only_csv_download(): void
    {
        $this->actAs('Cashier'); // sample download is not permission gated
        $resp = $this->get(route('master.customer-categories.import-sample'))->assertOk();
        $this->assertSame('Name,"App Access","Enable Loyalty","Discount Percent","Business Type",Status'."\n", $resp->streamedContent());
    }
}
