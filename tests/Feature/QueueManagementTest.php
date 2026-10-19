<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\QueueMonitor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['queue.default' => 'database']);
    }

    private function director(): User
    {
        return User::factory()->create()->assignRole('super-admin');
    }

    private function failedJob(string $class = 'App\\Jobs\\IssuePolicyDocument'): string
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid, 'connection' => 'database', 'queue' => 'default', 'failed_at' => now(),
            'payload' => json_encode(['uuid' => $uuid, 'displayName' => $class, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'maxTries' => 3, 'secret' => 'SECRET-PAYLOAD-DATA', 'data' => ['commandName' => $class, 'command' => serialize(new \App\Jobs\IssuePolicyDocument(1))]]),
            'exception' => "RuntimeException: SMTP server refused the message in /app/Mail.php:10\nStack trace:\n#0 /app/x.php(1): foo()",
        ]);

        return $uuid;
    }

    public function test_only_the_super_admin_can_see_or_use_the_queue_page(): void
    {
        $uuid = $this->failedJob();

        foreach (['accounts', 'compliance', 'hr', 'sales', 'investor'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->get(route('admin.queue'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.queue.pause'), ['password' => 'password'])->assertForbidden();
            $this->actingAs($user)->post(route('admin.queue.retry', $uuid))->assertForbidden();
            $this->actingAs($user)->post(route('admin.queue.run'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.queue.flush'), ['password' => 'password'])->assertForbidden();
        }
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertFalse(QueueMonitor::isPaused());

        $this->actingAs($this->director())->get(route('admin.queue'))->assertOk()->assertSee('Job queue');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('admin.queue'))->assertRedirect(route('login'));
    }

    public function test_the_page_shows_counts_and_failed_jobs_without_their_payload(): void
    {
        $this->failedJob();
        Queue::push(function () {});
        $page = $this->actingAs($this->director())->get(route('admin.queue'));

        $page->assertOk()->assertSee('IssuePolicyDocument')->assertSee('SMTP server refused the message')->assertSee('Failed jobs (1)')->assertDontSee('SECRET-PAYLOAD-DATA');
        $this->assertSame(1, QueueMonitor::counts()['waiting']);
    }

    public function test_pausing_needs_the_password_and_resume_restores_the_queue(): void
    {
        $director = $this->director();

        $this->actingAs($director)->post(route('admin.queue.pause'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertFalse(QueueMonitor::isPaused());

        $this->actingAs($director)->post(route('admin.queue.pause'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertTrue(QueueMonitor::isPaused());
        $this->actingAs($director)->get(route('admin.queue'))->assertSee('Paused')->assertSee('Resume the queue');
        $this->assertDatabaseHas('activity_log', ['description' => 'Queue paused', 'causer_id' => $director->id]);

        $this->actingAs($director)->post(route('admin.queue.resume'))->assertSessionHasNoErrors();
        $this->assertFalse(QueueMonitor::isPaused());
        $this->assertDatabaseHas('activity_log', ['description' => 'Queue resumed']);
    }

    public function test_run_now_processes_waiting_jobs_but_not_while_paused(): void
    {
        $director = $this->director();
        Cache::forget('ran');
        Queue::push(function () {
            Cache::put('ran', true);
        });
        $this->assertSame(1, QueueMonitor::counts()['waiting']);

        $this->actingAs($director)->post(route('admin.queue.pause'), ['password' => 'password']);
        $this->actingAs($director)->post(route('admin.queue.run'))->assertSessionHasErrors('queue');
        $this->assertNull(Cache::get('ran'));

        $this->actingAs($director)->post(route('admin.queue.resume'));
        $this->actingAs($director)->post(route('admin.queue.run'))->assertSessionHasNoErrors();
        $this->assertTrue(Cache::get('ran'));
        $this->assertSame(0, QueueMonitor::counts()['waiting']);
        $this->assertNotNull(QueueMonitor::lastActivity());
        $this->assertDatabaseHas('activity_log', ['description' => 'Queue run manually']);
    }

    public function test_a_failed_job_can_be_retried_and_goes_back_on_the_queue(): void
    {
        $uuid = $this->failedJob();
        $director = $this->director();

        $this->actingAs($director)->post(route('admin.queue.retry', $uuid))->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertDatabaseHas('activity_log', ['description' => 'Failed job retried', 'causer_id' => $director->id]);
        $this->actingAs($director)->post(route('admin.queue.retry', (string) Str::uuid()))->assertNotFound();
    }

    public function test_retry_all_requeues_every_failed_job(): void
    {
        $this->failedJob();
        $this->failedJob('App\\Notifications\\PaymentConfirmed');

        $this->actingAs($this->director())->post(route('admin.queue.retry-all'))->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(2, DB::table('jobs')->count());
    }

    public function test_deleting_failed_jobs_needs_the_password(): void
    {
        $uuid = $this->failedJob();
        $this->failedJob();
        $director = $this->director();

        $this->actingAs($director)->delete(route('admin.queue.forget', $uuid), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($director)->post(route('admin.queue.flush'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertSame(2, DB::table('failed_jobs')->count());

        $this->actingAs($director)->delete(route('admin.queue.forget', $uuid), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->actingAs($director)->post(route('admin.queue.flush'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertDatabaseHas('activity_log', ['description' => 'All failed jobs deleted']);
    }

    public function test_restart_signals_the_workers_and_is_audited(): void
    {
        $director = $this->director();
        Cache::forget('illuminate:queue:restart');

        $this->actingAs($director)->post(route('admin.queue.restart'))->assertSessionHasNoErrors();

        $this->assertNotNull(Cache::get('illuminate:queue:restart'));
        $this->assertDatabaseHas('activity_log', ['description' => 'Queue workers restarted']);
    }

    public function test_it_says_when_no_worker_seems_to_be_running(): void
    {
        $this->travelTo(now()->subMinutes(30));
        Queue::push(function () {});
        $this->travelBack();

        $this->assertSame('stalled', QueueMonitor::health(QueueMonitor::counts()));
        $this->actingAs($this->director())->get(route('admin.queue'))->assertSee('No worker seems to be running');

        QueueMonitor::touch();
        $this->assertSame('ok', QueueMonitor::health(QueueMonitor::counts()));
    }

    public function test_a_non_database_queue_is_explained_rather_than_broken(): void
    {
        config(['queue.default' => 'sync']);

        $this->actingAs($this->director())->get(route('admin.queue'))->assertOk()->assertSee('queue can be managed here');
    }
}
