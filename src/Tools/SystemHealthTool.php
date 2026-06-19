<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use CleaniqueCoders\LaravelMcpKit\Support\SystemHealth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Reachability of the core infrastructure (database, cache, queue, storage)
 * plus any app-defined checks registered with `Mcp::healthCheck(...)`.
 *
 * The actual probing lives in {@see SystemHealth} so this tool and the settings
 * UI report identical results. `spatie/laravel-health` results are folded in
 * when that package is installed.
 */
#[Name('system_health')]
#[Description('Report reachability of the database, cache, queue and storage, plus any app-defined connectivity checks. Read-only — runs lightweight probes only.')]
#[IsReadOnly]
class SystemHealthTool extends McpKitTool
{
    protected function ability(): string
    {
        return $this->configuredAbility('view-system');
    }

    public function handle(Request $request): Response
    {
        if ($this->authorizedUser($request) === null) {
            return $this->unauthorized();
        }

        return Response::json(app(SystemHealth::class)->run());
    }
}
