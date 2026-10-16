<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\InvestmentPlan;
use App\Models\User;
use App\Services\Payments\BankAccounts;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private array $good = ['bank_name' => 'Zenith Bank', 'account_number' => '1012345678', 'account_name' => 'AFISAB NIG. LTD', 'note' => 'Main collections', 'password' => 'password'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->director = User::factory()->create()->assignRole('super-admin');
    }

    public function test_super_admin_adds_and_edits_accounts_and_investors_see_them(): void
    {
        $this->actingAs($this->director)->get(route('admin.bank-accounts'))->assertOk()->assertSee('No account has been saved here yet');
        $this->actingAs($this->director)->post(route('admin.bank-accounts.store'), $this->good)->assertSessionHasNoErrors();

        $account = BankAccount::firstOrFail();
        $this->assertSame('1012345678', $account->account_number);
        $this->assertCount(1, app(BankAccounts::class)->active());

        $this->actingAs($this->director)->put(route('admin.bank-accounts.update', $account), ['account_number' => '2099999999', 'is_active' => '1'] + $this->good)->assertSessionHasNoErrors();
        $this->assertSame('2099999999', $account->fresh()->account_number);

        $investor = User::factory()->create()->assignRole('investor');
        $investor->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);
        $investment = app(SubscriptionService::class)->subscribe($investor, InvestmentPlan::where('slug', '3-month')->first(), 1_000_000_00);

        $this->actingAs($investor)->get(route('investor.investments.show', $investment))
            ->assertOk()->assertSee('Zenith Bank')->assertSee('2099999999')->assertSee('Main collections')->assertDontSee('1012345678');
    }

    public function test_changes_are_audited_with_old_and_new_values(): void
    {
        $this->actingAs($this->director)->post(route('admin.bank-accounts.store'), $this->good);
        $account = BankAccount::firstOrFail();
        $this->actingAs($this->director)->put(route('admin.bank-accounts.update', $account), ['account_number' => '2099999999', 'is_active' => '1'] + $this->good);

        $log = \Spatie\Activitylog\Models\Activity::where('description', 'Bank account changed')->firstOrFail();
        $this->assertSame('1012345678', $log->properties['before']['account_number']);
        $this->assertSame('2099999999', $log->properties['after']['account_number']);
        $this->assertSame($this->director->id, $log->causer_id);
    }

    public function test_password_and_account_number_are_validated(): void
    {
        $this->actingAs($this->director)->post(route('admin.bank-accounts.store'), ['password' => 'wrong'] + $this->good)->assertSessionHasErrors('password');
        $this->actingAs($this->director)->post(route('admin.bank-accounts.store'), ['account_number' => '12345'] + $this->good)->assertSessionHasErrors('account_number');
        $this->actingAs($this->director)->post(route('admin.bank-accounts.store'), ['account_number' => '0000000000'] + $this->good)->assertSessionHasErrors('account_number');
        $this->actingAs($this->director)->post(route('admin.bank-accounts.store'), ['bank_name' => ''] + $this->good)->assertSessionHasErrors('bank_name');
        $this->assertSame(0, BankAccount::count());
    }

    public function test_the_last_active_account_cannot_be_switched_off_but_one_can_once_another_exists(): void
    {
        $this->actingAs($this->director)->post(route('admin.bank-accounts.store'), $this->good);
        $first = BankAccount::firstOrFail();

        $this->actingAs($this->director)->put(route('admin.bank-accounts.update', $first), $this->good)->assertSessionHasErrors('bank'); // is_active omitted = off
        $this->assertTrue($first->fresh()->is_active);

        $this->actingAs($this->director)->post(route('admin.bank-accounts.store'), ['bank_name' => 'GTBank', 'account_number' => '0123456789'] + $this->good);
        $this->actingAs($this->director)->put(route('admin.bank-accounts.update', $first), $this->good)->assertSessionHasNoErrors();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertSame(['0123456789'], app(BankAccounts::class)->active()->pluck('account_number')->all());
    }

    public function test_only_the_super_admin_can_reach_it(): void
    {
        $accounts = User::factory()->create()->assignRole('accounts');
        $investor = User::factory()->create()->assignRole('investor');

        foreach ([$accounts, $investor] as $user) {
            $this->actingAs($user)->get(route('admin.bank-accounts'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.bank-accounts.store'), $this->good)->assertForbidden();
        }
        $this->assertSame(0, BankAccount::count());
    }

    public function test_environment_settings_are_the_fallback_until_an_account_is_saved(): void
    {
        config(['afisab.bank' => ['name' => 'Env Bank', 'account_number' => '0123456789', 'account_name' => 'AFISAB NIG. LTD']]);
        $this->assertSame('Env Bank', app(BankAccounts::class)->active()->first()['name']);

        $this->actingAs($this->director)->post(route('admin.bank-accounts.store'), $this->good);
        $this->assertSame('Zenith Bank', app(BankAccounts::class)->active()->first()['name']);
    }
}
