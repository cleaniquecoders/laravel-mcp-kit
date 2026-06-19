<?php

namespace Workbench\App\Livewire;

use CleaniqueCoders\LaravelMcpKit\Servers\ToolRegistry;
use CleaniqueCoders\LaravelMcpKit\Support\HealthRegistry;
use CleaniqueCoders\LaravelMcpKit\Support\McpToggle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

/**
 * Workbench harness for the MCP Configuration UI (issue #16).
 *
 * A build-free Livewire preview (Tailwind via CDN — the shipped, publishable
 * version will use Flux) that exercises the REAL backend: it flips the
 * cache-backed runtime toggle, and reads the effective config, health, and
 * registered tools straight from the kit's own support classes. Gated on
 * `manage-mcp`, exactly like the production UI will be.
 */
class McpSettings extends Component
{
    public function mount(): void
    {
        $this->ensureManageMcp();
    }

    public function enable(): void
    {
        $this->ensureManageMcp();
        McpToggle::enable();
    }

    public function disable(): void
    {
        $this->ensureManageMcp();
        McpToggle::disable();
    }

    protected function ensureManageMcp(): void
    {
        abort_unless(
            auth()->user()?->can(config('mcp-kit.abilities.manage-mcp', 'mcp-kit.manage-mcp')),
            403,
        );
    }

    public function render()
    {
        return view('livewire.mcp-settings', [
            'enabled' => McpToggle::enabled(),
            'master' => (bool) config('mcp-kit.enabled', true),
            'config' => $this->snapshot(),
            'abilities' => (array) config('mcp-kit.abilities', []),
            'health' => $this->health(),
            'tools' => $this->tools(),
        ]);
    }

    /**
     * The effective, read-only configuration (env + config/mcp-kit.php).
     *
     * @return array<string, mixed>
     */
    protected function snapshot(): array
    {
        $oauth = (bool) config('mcp-kit.web.oauth.enabled', false);

        return [
            'STDIO transport' => config('mcp-kit.local.enabled', true)
                ? 'on — handle: '.config('mcp-kit.local.handle', 'mcp-kit')
                : 'off',
            'STDIO local user' => config('mcp-kit.local.user') ?: '(none — stdio stays gated)',
            'HTTP transport' => config('mcp-kit.web.enabled', true)
                ? 'on — path: '.config('mcp-kit.web.path', 'mcp/tasks')
                : 'off',
            'HTTP throttle' => config('mcp-kit.web.throttle', '60,1'),
            'Computed middleware' => implode(', ', config('mcp-kit.web.middleware') ?? [
                $oauth ? 'auth:sanctum,api' : 'auth:sanctum',
                'throttle:'.config('mcp-kit.web.throttle', '60,1'),
            ]),
            'OAuth 2.1' => $oauth ? 'on' : 'off (token-only)',
            'Logs path' => config('mcp-kit.ops.logs.path'),
            'Export disk / TTL' => config('mcp-kit.ops.export.disk', 'local').' / '.config('mcp-kit.ops.export.ttl', 15).' min',
            'Token prefix' => config('mcp-kit.tokens.prefix', 'mcp-kit'),
        ];
    }

    /**
     * Core reachability checks + any app-defined Mcp::healthCheck() results.
     *
     * @return array<string, bool>
     */
    protected function health(): array
    {
        $checks = [
            'database' => $this->ok(fn () => DB::connection()->getPdo() !== null),
            'cache' => $this->ok(function (): bool {
                $key = 'mcp-kit:ui-health:'.Str::random(6);
                Cache::put($key, 'ok', 5);
                $ok = Cache::get($key) === 'ok';
                Cache::forget($key);

                return $ok;
            }),
            'queue' => $this->ok(fn () => app('queue')->connection()->size() >= 0),
            'storage' => is_dir(storage_path()) && is_writable(storage_path()),
        ];

        foreach (app(HealthRegistry::class)->run() as $name => $result) {
            $checks['app: '.$name] = (bool) ($result['healthy'] ?? false);
        }

        return $checks;
    }

    protected function ok(callable $probe): bool
    {
        try {
            return (bool) $probe();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The live tool registry: every registered tool, with the package-gated
     * (Tier-2) ones flagged.
     *
     * @return array<int, array{name: string, gated: bool}>
     */
    protected function tools(): array
    {
        $gated = ToolRegistry::packageGatedTools();

        return collect(ToolRegistry::tools())
            ->map(fn (string $tool): array => [
                'name' => class_basename($tool),
                'gated' => in_array($tool, $gated, true),
            ])
            ->values()
            ->all();
    }
}
