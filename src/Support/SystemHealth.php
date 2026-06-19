<?php

namespace CleaniqueCoders\LaravelMcpKit\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reachability of the core infrastructure (database, cache, queue, storage)
 * plus any app-defined checks registered with `Mcp::healthCheck(...)`, and
 * `spatie/laravel-health` results when that package is installed.
 *
 * Extracted so the `system_health` MCP tool and the settings UI report the
 * exact same checks — one implementation, two surfaces.
 */
class SystemHealth
{
    public function __construct(protected HealthRegistry $registry) {}

    /**
     * @return array{healthy: bool, checked_at: string, checks: array<string, array<string, mixed>>}
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        // App-defined connectivity checks (Mcp::healthCheck(...)).
        foreach ($this->registry->run() as $name => $result) {
            $checks['app:'.$name] = $result;
        }

        $checks = array_merge($checks, $this->spatieHealth());

        $healthy = collect($checks)->every(fn (array $c): bool => ($c['healthy'] ?? false) === true);

        return [
            'healthy' => $healthy,
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkDatabase(): array
    {
        try {
            $connection = DB::connection();
            $connection->getPdo();

            return [
                'healthy' => true,
                'driver' => $connection->getDriverName(),
                'name' => $connection->getDatabaseName(),
            ];
        } catch (Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkCache(): array
    {
        try {
            $key = 'mcp-kit:health:'.Str::random(8);
            Cache::put($key, 'ok', 5);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            return ['healthy' => $ok, 'store' => config('cache.default')];
        } catch (Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkQueue(): array
    {
        try {
            $connection = app('queue')->connection();
            $size = $connection->size();

            return [
                'healthy' => true,
                'connection' => config('queue.default'),
                'size' => $size,
            ];
        } catch (Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkStorage(): array
    {
        $path = storage_path();

        return [
            'healthy' => is_dir($path) && is_writable($path),
            'path' => $path,
            'disk' => config('filesystems.default'),
        ];
    }

    /**
     * Fold in spatie/laravel-health's latest stored results when the package
     * is installed. Guarded and defensive — never required, never fatal.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function spatieHealth(): array
    {
        $store = 'Spatie\\Health\\ResultStores\\ResultStore';

        if (! interface_exists($store) || ! app()->bound($store)) {
            return [];
        }

        try {
            $latest = app($store)->latestResults();

            if ($latest === null) {
                return [];
            }

            $results = [];

            foreach ($latest->storedCheckResults ?? [] as $result) {
                $results['spatie:'.Str::slug((string) ($result->name ?? 'check'))] = [
                    'healthy' => ($result->status ?? null) === 'ok',
                    'status' => $result->status ?? null,
                    'message' => $result->shortSummary ?? null,
                ];
            }

            return $results;
        } catch (Throwable) {
            return [];
        }
    }
}
