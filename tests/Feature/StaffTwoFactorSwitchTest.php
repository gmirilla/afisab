<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The super-admin switches two-factor authentication on or off per staff member; it is on for every new account. */
class StaffTwoFactorSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
    }

    private function director(): User
    {
        return User::factory()->create()->assignRole('super-admin');
    }

    private function staff(string $role = 'accounts', bool $required = false): User
    {
        $u = User::factory()->create()->assignRole($role);
        $u->forceFill(['two_factor_required' => $required])->save();

        return $u;
    }

    private function enrol(User $u): User
    {
        $u->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_recovery_codes' => encrypt('[]'), 'two_factor_confirmed_at' => now()])->save();

        return $u;
    }

    public function test_new_staff_accounts_require_two_factor_by_default(): void
    {
        $director = $this->director();

        $this->actingAs($director)->post(route('admin.users.store'), ['name' => 'New Person', 'email' => 'new@example.com', 'role' => 'accounts'])->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email', 'new@example.com')->first()->two_factor_required, 'no field sent: on');

        $this->actingAs($director)->post(route('admin.users.store'), ['name' => 'Second', 'email' => 'second@example.com', 'role' => 'sales', 'require_two_factor' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email', 'second@example.com')->first()->two_factor_required);

        $this->actingAs($director)->post(route('admin.users.store'), ['name' => 'Third', 'email' => 'third@example.com', 'role' => 'hr', 'require_two_factor' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse(User::where('email', 'third@example.com')->first()->two_factor_required, 'the super-admin chose to untick it');

        $this->actingAs($director)->get(route('admin.users'))->assertOk()->assertSee('name="require_two_factor"', false)->assertSee('Require two-factor authentication');
        $this->assertDatabaseHas('activity_log', ['description' => 'Staff account created']);
    }

    public function test_the_make_staff_command_requires_it_too(): void
    {
        $this->artisan('afisab:make-staff', ['name' => 'Cli User', 'email' => 'cli@example.com', 'role' => 'accounts'])->expectsQuestion('Password (min 10 chars, mixed case + number)', 'Str0ngPassword1')->assertSuccessful();

        $this->assertTrue(User::where('email', 'cli@example.com')->first()->two_factor_required);
    }

    public function test_the_super_admin_can_turn_it_on_and_off_for_a_person_with_their_password(): void
    {
        $director = $this->director();
        $person = $this->staff('accounts', required: false);

        $this->actingAs($director)->put(route('admin.users.two-factor', $person), ['required' => 1, 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertFalse($person->fresh()->two_factor_required);

        $this->actingAs($director)->put(route('admin.users.two-factor', $person), ['required' => 1, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertTrue($person->fresh()->two_factor_required);
        $this->assertDatabaseHas('activity_log', ['description' => 'Two-factor requirement turned on', 'causer_id' => $director->id, 'subject_id' => $person->id]);

        $this->actingAs($director)->put(route('admin.users.two-factor', $person), ['required' => 0, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertFalse($person->fresh()->two_factor_required);
        $this->assertDatabaseHas('activity_log', ['description' => 'Two-factor requirement turned off']);
    }

    public function test_the_requirement_is_enforced_per_person(): void
    {
        $required = $this->staff('accounts', required: true);
        $notRequired = $this->staff('accounts', required: false);

        $this->actingAs($required)->get(route('admin.funds'))->assertRedirect(route('account.security'));
        $this->actingAs($notRequired)->get(route('admin.funds'))->assertOk();
    }

    public function test_turning_it_on_holds_the_person_at_the_security_page_until_set_up_and_turning_it_off_frees_them(): void
    {
        $director = $this->director();
        $person = $this->staff('accounts', required: false);
        $this->actingAs($person)->get(route('admin.funds'))->assertOk();

        $this->actingAs($director)->put(route('admin.users.two-factor', $person), ['required' => 1, 'password' => 'password']);
        $this->actingAs($person->fresh())->get(route('admin.funds'))->assertRedirect(route('account.security'));

        $this->actingAs($director)->put(route('admin.users.two-factor', $person), ['required' => 0, 'password' => 'password']);
        $this->actingAs($person->fresh())->get(route('admin.funds'))->assertOk();
    }

    public function test_a_person_it_is_required_of_cannot_turn_it_off_but_one_it_is_not_may_use_it_voluntarily(): void
    {
        $required = $this->enrol($this->staff('accounts', required: true));
        $this->actingAs($required)->delete(route('account.security.disable'), ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertNotNull($required->fresh()->two_factor_confirmed_at);
        $this->actingAs($required)->get(route('account.security'))->assertSee('required');

        $optional = $this->enrol($this->staff('sales', required: false));
        $this->actingAs($optional)->delete(route('account.security.disable'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertNull($optional->fresh()->two_factor_confirmed_at);
    }

    public function test_turning_the_requirement_off_does_not_remove_two_factor_someone_already_uses(): void
    {
        $director = $this->director();
        $person = $this->enrol($this->staff('accounts', required: true));

        $this->actingAs($director)->put(route('admin.users.two-factor', $person), ['required' => 0, 'password' => 'password']);

        $this->assertNotNull($person->fresh()->two_factor_confirmed_at);
        $this->assertNotNull($person->fresh()->two_factor_secret);
    }

    public function test_only_the_super_admin_can_change_it_and_never_on_their_own_account_or_an_investor(): void
    {
        $person = $this->staff('accounts');
        foreach (['accounts', 'compliance', 'hr', 'sales', 'investor'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role))->put(route('admin.users.two-factor', $person), ['required' => 1, 'password' => 'password'])->assertForbidden();
        }
        $this->assertFalse($person->fresh()->two_factor_required);

        $director = $this->director();
        $this->actingAs($director)->put(route('admin.users.two-factor', $director), ['required' => 0, 'password' => 'password'])->assertSessionHasErrors('user');

        $investor = User::factory()->create()->assignRole('investor');
        $this->actingAs($director)->put(route('admin.users.two-factor', $investor), ['required' => 1, 'password' => 'password'])->assertNotFound();
        $this->assertFalse($investor->fresh()->two_factor_required);
    }

    public function test_the_users_page_shows_each_persons_state_and_hides_the_switch_on_your_own_row(): void
    {
        $director = $this->director();
        $this->staff('accounts', required: true);
        $this->enrol($this->staff('sales', required: false));

        $page = $this->actingAs($director)->get(route('admin.users'))->assertOk();

        $page->assertSee('Required')->assertSee('Not required')->assertSee('✔ set up')->assertSee('not set up');
        $this->assertSame(2, substr_count($page->getContent(), 'name="required"'), 'a switch for each other person, none for you');
    }

    public function test_investors_are_never_forced_whatever_the_flag_says(): void
    {
        $investor = User::factory()->create()->assignRole('investor');
        $investor->forceFill(['two_factor_required' => true])->save();
        $investor->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);

        $this->assertFalse($investor->fresh()->requiresTwoFactor());
        $this->actingAs($investor)->get(route('investor.dashboard'))->assertOk();
    }

    public function test_existing_staff_keep_what_applied_to_them_before_the_migration(): void
    {
        // The migration copies the old global setting onto existing staff; here we check its rule directly.
        $migration = require database_path('migrations/2026_11_09_100000_add_two_factor_required_to_users.php');
        $staff = User::factory()->create()->assignRole('accounts');
        $investor = User::factory()->create()->assignRole('investor');
        \Illuminate\Support\Facades\Schema::dropColumns('users', 'two_factor_required');

        putenv('AFISAB_REQUIRE_2FA=true');
        $_ENV['AFISAB_REQUIRE_2FA'] = 'true';
        $migration->up();
        putenv('AFISAB_REQUIRE_2FA');
        unset($_ENV['AFISAB_REQUIRE_2FA']);

        $this->assertSame(1, (int) \Illuminate\Support\Facades\DB::table('users')->where('id', $staff->id)->value('two_factor_required'));
        $this->assertSame(0, (int) \Illuminate\Support\Facades\DB::table('users')->where('id', $investor->id)->value('two_factor_required'), 'investors are not staff');
    }
}
