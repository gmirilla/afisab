<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\QueueMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\View\View;

/**
 * Manage the background-job queue that sends emails and issues documents. Super-admin only (queue.manage).
 *
 * On shared hosting the worker is a cron entry that starts every minute and exits when idle (deploy/crontab.txt),
 * so "start" and "stop" mean: pause (workers keep running but take no new jobs), resume, restart (workers finish their
 * current job and are replaced by the next cron run) and "run now" (process waiting jobs inside this request).
 * Pausing or clearing failed jobs needs the password; every action is audited. Job payloads are never shown.
 */
class QueueController extends Controller
{
    public function index(): View
    {
        $supported = QueueMonitor::supported();
        $counts = $supported ? QueueMonitor::counts() : null;

        return view('admin.queue', [
            'supported' => $supported,
            'connection' => QueueMonitor::connection(),
            'counts' => $counts,
            'health' => $supported ? QueueMonitor::health($counts) : null,
            'paused' => $supported && QueueMonitor::isPaused(),
            'lastActivity' => QueueMonitor::lastActivity(),
            'waiting' => $supported ? QueueMonitor::waiting() : collect(),
            'failed' => $supported ? QueueMonitor::failed() : collect(),
        ]);
    }

    public function pause(Request $request): RedirectResponse
    {
        $this->password($request);
        Queue::pause(QueueMonitor::connection(), QueueMonitor::queueName());
        $this->audit($request, 'Queue paused');

        return back()->with('status', 'The queue is paused. Workers will not start new jobs (emails and documents wait) until you resume it.');
    }

    public function resume(Request $request): RedirectResponse
    {
        Queue::resume(QueueMonitor::connection(), QueueMonitor::queueName());
        $this->audit($request, 'Queue resumed');

        return back()->with('status', 'The queue is running again.');
    }

    public function restart(Request $request): RedirectResponse
    {
        Artisan::call('queue:restart');
        $this->audit($request, 'Queue workers restarted');

        return back()->with('status', 'Workers were told to restart. They finish the job in hand and the next cron run starts fresh ones (this picks up new code after a deployment).');
    }

    /** Process waiting jobs now, inside this request, for at most 20 seconds: for when the cron worker is not running. */
    public function run(Request $request): RedirectResponse
    {
        if (QueueMonitor::isPaused()) {
            return back()->withErrors(['queue' => 'The queue is paused. Resume it first.']);
        }
        $before = QueueMonitor::counts()['waiting'];
        Artisan::call('queue:work', ['connection' => QueueMonitor::connection(), '--stop-when-empty' => true, '--max-time' => 20, '--max-jobs' => 100, '--tries' => 3]);
        $after = QueueMonitor::counts()['waiting'];
        $this->audit($request, 'Queue run manually', ['processed_approx' => max(0, $before - $after)]);

        return back()->with('status', 'Processed waiting jobs: '.max(0, $before - $after).' cleared, '.$after.' still waiting. Check the failed list for any that did not succeed.');
    }

    public function retry(Request $request, string $uuid): RedirectResponse
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        $this->audit($request, 'Failed job retried', ['uuid' => $uuid]);

        return back()->with('status', 'The job was put back on the queue. It runs on the next worker pass.');
    }

    public function retryAll(Request $request): RedirectResponse
    {
        $n = DB::table('failed_jobs')->count();
        Artisan::call('queue:retry', ['id' => ['all']]);
        $this->audit($request, 'All failed jobs retried', ['count' => $n]);

        return back()->with('status', "{$n} failed job(s) were put back on the queue.");
    }

    public function forget(Request $request, string $uuid): RedirectResponse
    {
        $this->password($request);
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);
        Artisan::call('queue:forget', ['id' => $uuid]);
        $this->audit($request, 'Failed job deleted', ['uuid' => $uuid]);

        return back()->with('status', 'The failed job was deleted.');
    }

    public function flush(Request $request): RedirectResponse
    {
        $this->password($request);
        $n = DB::table('failed_jobs')->count();
        Artisan::call('queue:flush');
        $this->audit($request, 'All failed jobs deleted', ['count' => $n]);

        return back()->with('status', "{$n} failed job(s) were deleted.");
    }

    private function password(Request $request): void
    {
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Your password is incorrect.']);
    }

    /** @param  array<string, mixed>  $properties */
    private function audit(Request $request, string $what, array $properties = []): void
    {
        activity()->causedBy($request->user())->withProperties($properties)->log($what);
    }
}
