<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\QueueMonitor;
use App\Support\SchedulerHealth;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** The /health monitoring URL, and the "require two-factor for all staff" button. */
class HealthAndRequireAllTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['afisab.health_token' => 'secret-monitor-token', 'queue.default' => 'database']);
        Cache::flush();
        SchedulerHealth::beat();
    }

    private function health(array $query = ['token' => 'secret-monitor-token'])
    {
        return $this->getJson('/health?'.http_build_query($query));
    }

    private function stuckJob(int $minutes = 30): void
    {
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes($minutes)->timestamp, 'created_at' => now()->subMinutes($minutes)->timestamp]);
    }

    // ---- /health ----

    public function test_it_is_a_plain_404_without_the_right_token_and_when_switched_off(): void
    {
        $this->health([])->assertNotFound();
        $this->health(['token' => 'wrong'])->assertNotFound();
        $this->getJson('/health')->assertNotFound();

        config(['afisab.health_token' => null]);
        $this->health(['token' => ''])->assertNotFound();
        $this->health(['token' => 'secret-monitor-token'])->assertNotFound();
    }

    public function test_it_says_200_when_everything_is_fine_and_sends_the_token_as_a_header_too(): void
    {
        $this->health()->assertOk()->assertHeader('cache-control', 'no-store, private')->assertJson(['status' => 'ok', 'checks' => ['database' => ['ok' => true], 'scheduler' => ['ok' => true], 'queue' => ['ok' => true], 'mail' => ['ok' => true]]]);

        $this->withHeader('Authorization', 'Bearer secret-monitor-token')->getJson('/health')->assertOk();
    }

    public function test_emails_stuck_in_the_queue_make_it_503_and_say_so(): void
    {
        $this->stuckJob(30);

        $this->health()->assertStatus(503)->assertJson(['status' => 'degraded', 'checks' => ['queue' => ['ok' => false]]])
            ->assertJsonPath('checks.queue.detail', fn ($d) => str_contains($d, 'waiting 30 minutes'));
    }

    public function test_a_recent_job_is_fine_but_failed_jobs_and_a_paused_queue_are_not(): void
    {
        $this->stuckJob(2);
        $this->health()->assertOk();

        DB::table('failed_jobs')->insert(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);
        $this->health()->assertStatus(503)->assertJsonPath('checks.queue.detail', fn ($d) => str_contains($d, '1 queued job(s) failed'));

        DB::table('failed_jobs')->truncate();
        Queue::pause('database', QueueMonitor::queueName());
        $this->health()->assertStatus(503)->assertJsonPath('checks.queue.detail', fn ($d) => str_contains($d, 'paused'));
    }

    public function test_a_stopped_scheduler_is_reported(): void
    {
        Cache::put(SchedulerHealth::KEY, now()->subMinutes(40)->timestamp, now()->addHour());

        $this->health()->assertStatus(503)->assertJsonPath('checks.scheduler.ok', false)->assertJsonPath('checks.scheduler.detail', fn ($d) => str_contains($d, 'stopped'));
    }

    public function test_in_production_a_missing_heartbeat_and_a_log_mailer_are_problems(): void
    {
        $this->app['env'] = 'production';
        Cache::forget(SchedulerHealth::KEY);
        config(['mail.default' => 'log']);

        $this->health()->assertStatus(503)->assertJsonPath('checks.scheduler.ok', false)->assertJsonPath('checks.mail.ok', false)->assertJsonPath('checks.database.ok', true);

        config(['mail.default' => 'smtp']);
        SchedulerHealth::beat();
        $this->health()->assertOk();
    }

    public function test_the_command_reports_the_same_thing(): void
    {
        $this->artisan('afisab:health')->assertSuccessful();

        $this->stuckJob(30);
        $this->artisan('afisab:health')->assertFailed();
    }

    public function test_the_url_is_kept_out_of_search_engines_and_preflight_nags_until_it_is_set(): void
    {
        $this->get(route('robots'))->assertSee('Disallow: /health');

        config(['afisab.health_token' => null]);
        $this->artisan('afisab:preflight')->expectsOutputToContain('Monitoring URL (/health) is switched on');
    }

    // ---- require two-factor for all staff ----

    private function staff(string $role, bool $required): User
    {
        $u = User::factory()->create()->assignRole($role);
        $u->forceFill(['two_factor_required' => $required])->save();

        return $u;
    }

    private function enrol(User $u): User
    {
        $u->forceFill(['two_factor_secret' => encrypt('S'), 'two_factor_recovery_codes' => encrypt('[]'), 'two_factor_confirmed_at' => now()])->save();

        return $u;
    }

    public function test_the_button_requires_it_of_every_staff_member_and_nobody_else(): void
    {
        $director = $this->staff('super-admin', required: false);
        $accounts = $this->staff('accounts', required: false);
        $hr = $this->staff('hr', required: true);
        $investor = User::factory()->create()->assignRole('investor');

        $this->actingAs($director)->post(route('admin.users.two-factor.all'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertFalse($accounts->fresh()->two_factor_required);

        $this->actingAs($director)->post(route('admin.users.two-factor.all'), ['password' => 'password'])->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'required of 2 more') && str_contains($m, '2 of them will be asked'));

        foreach ([$director, $accounts, $hr] as $person) {
            $this->assertTrue($person->fresh()->two_factor_required);
        }
        $this->assertFalse($investor->fresh()->two_factor_required, 'investors are never forced');
        $this->assertDatabaseHas('activity_log', ['description' => 'Two-factor requirement turned on for all staff', 'causer_id' => $director->id]);
    }

    public function test_people_who_already_have_it_are_not_asked_again_and_a_second_press_does_nothing(): void
    {
        $director = $this->enrol($this->staff('super-admin', required: true));
        $enrolled = $this->enrol($this->staff('accounts', required: false));

        $this->actingAs($director)->post(route('admin.users.two-factor.all'), ['password' => 'password'])
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'required of 1 more') && str_contains($m, '0 of them will be asked'));
        $this->actingAs($director)->post(route('admin.users.two-factor.all'), ['password' => 'password'])->assertSessionHas('status', 'Two-factor authentication was already required of every staff member.');
    }

    public function test_it_holds_people_at_the_security_page_afterwards(): void
    {
        $director = $this->staff('super-admin', required: false);
        $accounts = $this->staff('accounts', required: false);
        $this->actingAs($accounts)->get(route('admin.funds'))->assertOk();

        $this->actingAs($director)->post(route('admin.users.two-factor.all'), ['password' => 'password']);

        $this->actingAs($accounts->fresh())->get(route('admin.funds'))->assertRedirect(route('account.security'));
    }

    public function test_only_the_super_admin_can_press_it_and_the_page_shows_it_only_when_needed(): void
    {
        $director = $this->enrol($this->staff('super-admin', required: true));
        $accounts = $this->staff('accounts', required: false);

        foreach (['accounts', 'compliance', 'hr', 'sales', 'investor'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role))->post(route('admin.users.two-factor.all'), ['password' => 'password'])->assertForbidden();
        }
        $this->assertFalse($accounts->fresh()->two_factor_required);

        $this->actingAs($director)->get(route('admin.users'))->assertOk()->assertSee('Require for all staff')->assertSee('members are not required to use two-factor');
        $this->actingAs($director)->post(route('admin.users.two-factor.all'), ['password' => 'password']);
        $this->actingAs($director)->get(route('admin.users'))->assertOk()->assertDontSee('Require for all staff')->assertSee('required of every staff member');
    }
}
