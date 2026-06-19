<?php

namespace Workbench\App\Livewire;

use CleaniqueCoders\LaravelMcpKit\Support\McpConfigSnapshot;
use CleaniqueCoders\LaravelMcpKit\Support\McpToggle;
use CleaniqueCoders\LaravelMcpKit\Support\SystemHealth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Workbench harness for the MCP Configuration UI (issue #16).
 *
 * A build-free Livewire preview (Tailwind via CDN — the publishable version
 * uses Flux, see stubs/Livewire/McpSettings.php.stub) over the SAME read models
 * the shipped UI and `mcp-kit:doctor` use: McpConfigSnapshot, SystemHealth, and
 * the cache-backed McpToggle. Gated on `manage-mcp`.
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
            Gate::allows(config('mcp-kit.abilities.manage-mcp', 'mcp-kit.manage-mcp')),
            403,
        );
    }

    public function render()
    {
        $snapshot = app(McpConfigSnapshot::class);

        return view('livewire.mcp-settings', [
            'enabled' => McpToggle::enabled(),
            'master' => (bool) config('mcp-kit.enabled', true),
            'config' => $snapshot->effectiveConfig(),
            'abilities' => $snapshot->abilities(),
            'tools' => $snapshot->tools(),
            'health' => collect(app(SystemHealth::class)->run()['checks'])
                ->map(fn (array $check): bool => (bool) ($check['healthy'] ?? false))
                ->all(),
        ]);
    }
}
