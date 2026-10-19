<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * What the background-job queue is doing, for the super-admin's queue page. Reads only counts and job class names:
 * a job's payload can hold personal data, so it is never shown.
 */
class QueueMonitor
{
    public const ACTIVITY_KEY = 'afisab.queue.last_activity';

    /** A job waiting longer than this with no worker activity means no worker is running. */
    public const STALLED_AFTER_MINUTES = 5;

    public static function touch(): void
    {
        Cache::put(self::ACTIVITY_KEY, now()->timestamp, now()->addDays(2));
    }

    public static function connection(): string
    {
        return (string) config('queue.default');
    }

    /** Only the database queue can be inspected and managed from here. */
    public static function supported(): bool
    {
        return self::connection() === 'database' && Schema::hasTable('jobs') && Schema::hasTable('failed_jobs');
    }

    public static function queueName(): string
    {
        return (string) config('queue.connections.database.queue', 'default');
    }

    public static function isPaused(): bool
    {
        return self::supported() && Queue::isPaused(self::connection(), self::queueName());
    }

    public static function lastActivity(): ?Carbon
    {
        $at = Cache::get(self::ACTIVITY_KEY);

        return $at ? Carbon::createFromTimestamp((int) $at) : null;
    }

    /** @return array{waiting:int, delayed:int, running:int, failed:int, oldest:?Carbon} */
    public static function counts(): array
    {
        $now = now()->timestamp;
        $jobs = DB::table('jobs');

        return [
            'waiting' => (clone $jobs)->whereNull('reserved_at')->where('available_at', '<=', $now)->count(),
            'delayed' => (clone $jobs)->whereNull('reserved_at')->where('available_at', '>', $now)->count(),
            'running' => (clone $jobs)->whereNotNull('reserved_at')->count(),
            'failed' => DB::table('failed_jobs')->count(),
            'oldest' => ($t = (clone $jobs)->whereNull('reserved_at')->min('available_at')) ? Carbon::createFromTimestamp((int) $t) : null,
        ];
    }

    /** ok | paused | stalled | idle: a one-word health summary. */
    public static function health(array $counts): string
    {
        if (self::isPaused()) {
            return 'paused';
        }
        $stalled = $counts['oldest'] && $counts['oldest']->lt(now()->subMinutes(self::STALLED_AFTER_MINUTES))
            && (! self::lastActivity() || self::lastActivity()->lt(now()->subMinutes(self::STALLED_AFTER_MINUTES)));

        return $stalled ? 'stalled' : ($counts['waiting'] + $counts['running'] === 0 ? 'idle' : 'ok');
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    public static function waiting(int $limit = 25)
    {
        return DB::table('jobs')->orderBy('id')->limit($limit)->get(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'])
            ->map(fn ($j) => (object) [
                'id' => $j->id, 'name' => self::jobName($j->payload), 'queue' => $j->queue, 'attempts' => (int) $j->attempts,
                'running' => $j->reserved_at !== null, 'available_at' => Carbon::createFromTimestamp((int) $j->available_at),
                'created_at' => Carbon::createFromTimestamp((int) $j->created_at),
            ]);
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    public static function failed(int $limit = 50)
    {
        return DB::table('failed_jobs')->orderByDesc('id')->limit($limit)->get(['id', 'uuid', 'queue', 'payload', 'exception', 'failed_at'])
            ->map(fn ($j) => (object) [
                'uuid' => $j->uuid, 'name' => self::jobName($j->payload), 'queue' => $j->queue, 'failed_at' => Carbon::parse($j->failed_at),
                'reason' => self::reason((string) $j->exception), 'detail' => self::detail((string) $j->exception),
            ]);
    }

    /** "App\Jobs\IssuePolicyDocument" -> "IssuePolicyDocument"; a queued notification -> its own class name. */
    public static function jobName(string $payload): string
    {
        $data = json_decode($payload, true) ?: [];
        $class = (string) ($data['displayName'] ?? $data['job'] ?? 'Unknown job');

        return class_basename($class);
    }

    /** The first line of the exception: what went wrong, without the stack trace. */
    private static function reason(string $exception): string
    {
        return \Illuminate\Support\Str::limit(trim(strtok($exception, "\n") ?: 'No reason recorded'), 300);
    }

    /** The first few lines of the stack trace, for support. Paths only, no payload. */
    private static function detail(string $exception): string
    {
        return \Illuminate\Support\Str::limit(implode("\n", array_slice(explode("\n", $exception), 0, 8)), 1500);
    }
}
