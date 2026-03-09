<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class HealthCheckController extends Controller
{
    /**
     * Lightweight readiness probe for internal use.
     *
     * Verifies database connectivity, cache availability, and queue
     * configuration presence. Designed for load-balancer / orchestrator
     * health checks — not for public exposure.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueueConfig(),
        ];

        $healthy = collect($checks)->every(fn (array $c) => $c['ok']);

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    /**
     * @return array{ok: bool, driver: string, error?: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'ok' => true,
                'driver' => (string) config('database.default'),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'driver' => (string) config('database.default'),
                'error' => 'Connection failed',
            ];
        }
    }

    /**
     * @return array{ok: bool, driver: string, error?: string}
     */
    private function checkCache(): array
    {
        $driver = (string) config('cache.default');

        try {
            $testKey = '_readiness_probe_'.bin2hex(random_bytes(4));
            Cache::put($testKey, true, 5);
            $result = Cache::get($testKey) === true;
            Cache::forget($testKey);

            return [
                'ok' => $result,
                'driver' => $driver,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'driver' => $driver,
                'error' => 'Cache unavailable',
            ];
        }
    }

    /**
     * @return array{ok: bool, driver: string}
     */
    private function checkQueueConfig(): array
    {
        $driver = (string) config('queue.default');
        $validDrivers = ['sync', 'database', 'redis', 'beanstalkd', 'sqs'];

        return [
            'ok' => in_array($driver, $validDrivers, true),
            'driver' => $driver,
        ];
    }
}
