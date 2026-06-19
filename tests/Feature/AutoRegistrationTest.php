<?php

use CleaniqueCoders\LaravelMcpKit\Servers\ToolRegistry;
use CleaniqueCoders\LaravelMcpKit\Tools\IssueMcpTokenTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListActivitiesTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListAuditsTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListMcpTokensTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListRolesTool;
use CleaniqueCoders\LaravelMcpKit\Tools\RevokeMcpTokenTool;
use CleaniqueCoders\LaravelMcpKit\Tools\WhoAmITool;
use Laravel\Mcp\Request;

it('always registers the Tier-1 generic tools', function () {
    expect(ToolRegistry::tools())->toContain(WhoAmITool::class);
});

it('registers the sanctum token tools when sanctum is installed and migrated', function () {
    // sanctum is a dev dependency and the personal_access_tokens table exists
    // in the test environment, so the token tools auto-register.
    expect(IssueMcpTokenTool::isAvailable())->toBeTrue()
        ->and(ToolRegistry::tools())
        ->toContain(IssueMcpTokenTool::class)
        ->toContain(ListMcpTokensTool::class)
        ->toContain(RevokeMcpTokenTool::class);
});

it('does NOT register package-gated tools whose package is absent', function () {
    // owen-it/laravel-auditing, spatie/laravel-permission and
    // spatie/laravel-activitylog are not installed in the kit, so their tools
    // stay out of the registry — the kit degrades gracefully.
    expect(ListAuditsTool::isAvailable())->toBeFalse()
        ->and(ListRolesTool::isAvailable())->toBeFalse()
        ->and(ListActivitiesTool::isAvailable())->toBeFalse()
        ->and(ToolRegistry::tools())
        ->not->toContain(ListAuditsTool::class)
        ->not->toContain(ListRolesTool::class)
        ->not->toContain(ListActivitiesTool::class);
});

it('an absent-package tool degrades to a graceful error, never a fatal, if invoked directly', function () {
    // It is unreachable through the server (not registered), but if a host
    // force-registers or calls it, handle() must return an error — not a
    // "class not found" fatal.
    $response = (new ListAuditsTool)->handle(new Request([]));

    expect($response->isError())->toBeTrue();
});
