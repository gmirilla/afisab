<?php

namespace Tests\Feature;

use App\Support\HealthPinger;
use App\Support\SchedulerHealth;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** The app reports to a Healthchecks.io-style service, so silence raises the alarm. */
class HealthPingTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://hc-ping.com/11111111-2222-3333-4444-555555555555';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['afisab.healthcheck_ping_url' => self::URL, 'queue.default' => 'database']);
        Cache::flush();
        SchedulerHealth::beat();
    }

    private function stuckJob(): void
    {
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(30)->timestamp, 'created_at' => now()->subMinutes(30)->timestamp]);
    }

    public function test_nothing_is_sent_when_no_url_is_set(): void
    {
        Http::fake();
        config(['afisab.healthcheck_ping_url' => null]);

        $this->assertSame('off', HealthPinger::ping());
        Http::assertNothingSent();
        $this->artisan('afisab:health-ping')->expectsOutputToContain('nothing sent')->assertSuccessful();
    }

    public function test_when_everything_is_fine_the_success_address_is_pinged(): void
    {
        Http::fake();

        $this->assertSame('ok', HealthPinger::ping());

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === self::URL);
    }

    public function test_a_failing_check_pings_the_fail_address_with_the_reason(): void
    {
        Http::fake();
        $this->stuckJob();

        $this->assertSame('fail', HealthPinger::ping());

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::URL.'/fail'
            && str_contains($r->body(), 'queue: emails or documents have been waiting 30 minutes') && ! str_contains($r->body(), 'hc-ping.com'));
    }

    public function test_a_trailing_slash_on_the_url_is_tolerated(): void
    {
        Http::fake();
        config(['afisab.healthcheck_ping_url' => self::URL.'/']);
        $this->stuckJob();

        HealthPinger::ping();

        Http::assertSent(fn (Request $r) => $r->url() === self::URL.'/fail');
    }

    public function test_a_network_failure_never_breaks_the_scheduler_and_never_logs_the_url(): void
    {
        Log::spy();
        Http::fake(['*' => fn () => throw new ConnectionException('cURL error 6: could not resolve host')]);

        $this->assertSame('error', HealthPinger::ping());

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => $message === 'The health ping could not be delivered' && ! str_contains(json_encode($context), 'hc-ping'));
    }

    public function test_the_command_reports_what_it_did_and_is_scheduled_every_five_minutes(): void
    {
        Http::fake();

        $this->artisan('afisab:health-ping')->expectsOutputToContain('success ping sent')->assertSuccessful();
        $this->stuckJob();
        $this->artisan('afisab:health-ping')->expectsOutputToContain('failure ping sent')->assertSuccessful();
        $this->artisan('schedule:list')->expectsOutputToContain('afisab:health-ping')->assertSuccessful();
    }

    public function test_preflight_nags_until_it_is_set_up(): void
    {
        config(['afisab.healthcheck_ping_url' => null]);

        $this->artisan('afisab:preflight')->expectsOutputToContain("Dead-man's-switch ping (Healthchecks.io) is set up");
    }
}
