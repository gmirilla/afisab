<x-layout title="Job queue">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Job queue</h1>
    <p class="mb-4 text-sm text-gray-600">The background worker sends emails and issues receipts and agreements. On this host it is started every minute by cron and stops when there is nothing to do, so <strong>pause</strong> stops it taking new jobs, <strong>restart</strong> replaces running workers (use it after a deployment), and <strong>run now</strong> processes waiting jobs immediately. Job contents are never shown. Every action here is recorded.</p>
    @if($errors->any() && ! $errors->has('password'))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</p>@endif
    @if($errors->has('password'))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('password') }}</p>@endif

    @unless($supported)
        <x-card><p class="text-sm text-amber-700">The queue connection is <strong>{{ $connection }}</strong>. Only the <strong>database</strong> queue can be managed here. Set <code>QUEUE_CONNECTION=database</code> in the environment file.</p></x-card>
    @else
        @php($tone = ['ok' => 'bg-emerald-50 text-emerald-800', 'idle' => 'bg-emerald-50 text-emerald-800', 'paused' => 'bg-amber-50 text-amber-800', 'stalled' => 'bg-red-50 text-red-800'][$health])
        <div class="mb-4 rounded p-3 text-sm {{ $tone }}">
            @switch($health)
                @case('paused') <strong>Paused.</strong> Emails and documents are waiting. Resume the queue to send them. @break
                @case('stalled') <strong>No worker seems to be running.</strong> Jobs have waited more than {{ \App\Support\QueueMonitor::STALLED_AFTER_MINUTES }} minutes. Check the queue cron entry, or press "Run now". @break
                @case('idle') <strong>Idle.</strong> Nothing is waiting. @break
                @default <strong>Working.</strong> Jobs are being processed.
            @endswitch
            <span class="ml-2 text-xs opacity-80">Last job handled: {{ $lastActivity ? $lastActivity->diffForHumans() : 'not seen since the last server restart' }}</span>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-card><p class="text-sm text-gray-500">Waiting</p><p class="text-2xl font-black">{{ $counts['waiting'] }}</p>@if($counts['oldest'])<p class="text-xs text-gray-500">oldest {{ $counts['oldest']->diffForHumans() }}</p>@endif</x-card>
            <x-card><p class="text-sm text-gray-500">Running now</p><p class="text-2xl font-black">{{ $counts['running'] }}</p></x-card>
            <x-card><p class="text-sm text-gray-500">Scheduled for later</p><p class="text-2xl font-black">{{ $counts['delayed'] }}</p></x-card>
            <x-card><p class="text-sm text-gray-500">Failed</p><p class="text-2xl font-black {{ $counts['failed'] ? 'text-red-700' : '' }}">{{ $counts['failed'] }}</p></x-card>
        </div>

        <x-card class="mb-6">
            <h2 class="mb-3 font-black text-brand-700">Controls</h2>
            <div class="flex flex-wrap items-end gap-3">
                @if($paused)
                    <form method="POST" action="{{ route('admin.queue.resume') }}">@csrf<x-btn>Resume the queue</x-btn></form>
                @else
                    <form method="POST" action="{{ route('admin.queue.pause') }}" class="flex items-end gap-2" data-confirm="Pause the queue? Emails and documents will wait until you resume it.">@csrf
                        <input type="password" name="password" placeholder="Your password" required autocomplete="current-password" class="w-40 rounded border px-2 py-1.5 text-sm"><x-btn variant="danger">Pause</x-btn></form>
                @endif
                <form method="POST" action="{{ route('admin.queue.run') }}" data-confirm="Process waiting jobs now? This can take up to 20 seconds.">@csrf<x-btn variant="ghost">Run now</x-btn></form>
                <form method="POST" action="{{ route('admin.queue.restart') }}" data-confirm="Restart the workers? They finish the job in hand first.">@csrf<x-btn variant="ghost">Restart workers</x-btn></form>
            </div>
        </x-card>

        <x-card class="mb-6">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-black text-brand-700">Failed jobs ({{ $counts['failed'] }})</h2>
                @if($counts['failed'])
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('admin.queue.retry-all') }}" data-confirm="Retry every failed job?">@csrf<x-btn variant="ghost">Retry all</x-btn></form>
                        <form method="POST" action="{{ route('admin.queue.flush') }}" class="flex items-center gap-1" data-confirm="Delete ALL failed jobs? They cannot be retried afterwards.">@csrf
                            <input type="password" name="password" placeholder="Your password" required class="w-32 rounded border px-2 py-1 text-xs"><x-btn variant="danger">Delete all</x-btn></form>
                    </div>
                @endif
            </div>
            @forelse($failed as $f)
                <div class="border-b py-3 last:border-0">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div><p class="font-bold">{{ $f->name }} <span class="font-normal text-gray-500">· {{ $f->queue }} · {{ $f->failed_at->format('d M Y H:i') }}</span></p>
                            <p class="text-sm text-red-700">{{ $f->reason }}</p></div>
                        <div class="flex items-center gap-1">
                            <form method="POST" action="{{ route('admin.queue.retry', $f->uuid) }}">@csrf<x-btn variant="ghost">Retry</x-btn></form>
                            <form method="POST" action="{{ route('admin.queue.forget', $f->uuid) }}" class="flex items-center gap-1" data-confirm="Delete this failed job?">@csrf @method('DELETE')
                                <input type="password" name="password" placeholder="Your password" required class="w-28 rounded border px-2 py-1 text-xs"><x-btn variant="danger">Delete</x-btn></form>
                        </div>
                    </div>
                    <details class="mt-1 text-xs text-gray-500"><summary class="cursor-pointer">Technical detail</summary><pre class="mt-1 overflow-x-auto whitespace-pre-wrap rounded bg-stone-50 p-2">{{ $f->detail }}</pre></details>
                </div>
            @empty
                <p class="text-sm text-gray-500">No failed jobs.</p>
            @endforelse
            @if($counts['failed'] > $failed->count())<p class="mt-2 text-xs text-gray-500">Showing the latest {{ $failed->count() }}.</p>@endif
        </x-card>

        <x-card>
            <h2 class="mb-3 font-black text-brand-700">Waiting and running jobs</h2>
            <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Job</th><th>Queue</th><th>State</th><th>Tries</th><th>Queued</th></tr></thead><tbody>
            @forelse($waiting as $j)
                <tr class="border-b last:border-0"><td class="py-2">{{ $j->name }}</td><td>{{ $j->queue }}</td>
                    <td>{{ $j->running ? 'Running' : ($j->available_at->isFuture() ? 'Scheduled '.$j->available_at->diffForHumans() : 'Waiting') }}</td><td>{{ $j->attempts }}</td><td class="text-gray-600">{{ $j->created_at->diffForHumans() }}</td></tr>
            @empty
                <tr><td colspan="5" class="py-4 text-center text-gray-500">Nothing is waiting.</td></tr>
            @endforelse
            </tbody></table>
        </x-card>
    @endunless
</x-layout>
