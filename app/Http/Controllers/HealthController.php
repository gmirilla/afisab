<?php

namespace App\Http\Controllers;

use App\Support\HealthCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /health?token=...  for an uptime monitor. Answers 200 when everything is fine and 503 when something is not, so any
 * monitor that alerts on a non-200 (UptimeRobot, Better Stack, a cron curl) tells someone straight away. Without
 * AFISAB_HEALTH_TOKEN set, or with the wrong token, it is a plain 404: it does not reveal that it exists. The answer
 * names the failing parts and nothing secret.
 */
class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('afisab.health_token');
        $given = (string) ($request->bearerToken() ?: $request->query('token', ''));
        abort_unless($expected !== '' && hash_equals($expected, $given), 404);

        $result = HealthCheck::run();

        return response()->json($result + ['checked_at' => now()->toIso8601String()], $result['status'] === 'ok' ? 200 : 503, ['Cache-Control' => 'no-store']);
    }
}
