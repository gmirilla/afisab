<?php

use App\Support\SchedulerHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Proves the host's every-minute cron is alive; the back office warns if this goes quiet.
Schedule::call(fn () => SchedulerHealth::beat())->name('heartbeat')->everyMinute();

Schedule::command('afisab:process-maturities')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('afisab:process-withdrawals')->dailyAt('06:30')->withoutOverlapping();

// Monthly profit distribution: calculate on the 27th (end of day) so approval and payment can finish by month end.
// monthlyOn(27) is safe in every month (February has 28 days). The 09:00 daily check catches a missed run and chases deadlines.
Schedule::command('afisab:run-distribution')->monthlyOn((int) config('afisab.distribution_cutoff_day', 27), '23:30')->withoutOverlapping();
Schedule::command('afisab:distribution-reminders')->dailyAt('09:00')->withoutOverlapping();
