<?php

namespace CleaniqueCoders\LaravelMcpKit\Commands;

use CleaniqueCoders\LaravelMcpKit\Support\McpToggle;
use Illuminate\Console\Command;

/**
 * Turn the MCP server on or off at runtime without a redeploy.
 *
 *   php artisan mcp-kit:toggle on
 *   php artisan mcp-kit:toggle off
 *   php artisan mcp-kit:toggle status   # or no argument
 *
 * The env flag MCP_KIT_ENABLED remains the master kill-switch: while it is off
 * this toggle cannot turn MCP on. Flipping the toggle clears the route cache so
 * the change takes effect on the next request — see Support\McpToggle.
 */
class ToggleCommand extends Command
{
    protected $signature = 'mcp-kit:toggle {state? : on, off, or status}';

    protected $description = 'Enable or disable the MCP server at runtime (cache-backed)';

    public function handle(): int
    {
        $state = strtolower((string) ($this->argument('state') ?? 'status'));

        return match ($state) {
            'on', 'enable', 'enabled' => $this->apply(true),
            'off', 'disable', 'disabled' => $this->apply(false),
            'status', '' => $this->status(),
            default => $this->invalid($state),
        };
    }

    protected function apply(bool $enabled): int
    {
        if ($enabled && ! config('mcp-kit.enabled', true)) {
            $this->components->warn(
                'MCP_KIT_ENABLED is false — the env master switch keeps MCP off regardless of this toggle.'
            );
        }

        $enabled ? McpToggle::enable() : McpToggle::disable();

        $this->components->info('MCP runtime toggle set to '.($enabled ? 'ON' : 'OFF').'.');

        return $this->status();
    }

    protected function status(): int
    {
        $this->components->twoColumnDetail('Master switch (MCP_KIT_ENABLED)', config('mcp-kit.enabled', true) ? '<fg=green>on</>' : '<fg=red>off</>');
        $this->components->twoColumnDetail('Effective state', McpToggle::enabled() ? '<fg=green>ENABLED</>' : '<fg=red>DISABLED</>');

        return self::SUCCESS;
    }

    protected function invalid(string $state): int
    {
        $this->components->error("Unknown state [{$state}]. Use: on, off, or status.");

        return self::FAILURE;
    }
}
