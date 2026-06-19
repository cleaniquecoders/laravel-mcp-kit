<?php

use CleaniqueCoders\LaravelMcpKit\Support\HealthRegistry;
use CleaniqueCoders\LaravelMcpKit\Support\McpConfigSnapshot;
use CleaniqueCoders\LaravelMcpKit\Support\SystemHealth;

it('exposes the effective configuration as label => value pairs', function () {
    expect(app(McpConfigSnapshot::class)->effectiveConfig())
        ->toHaveKeys(['STDIO transport', 'HTTP transport', 'Computed middleware', 'OAuth 2.1', 'Token prefix']);
});

it('returns doctor rows with a valid level each', function () {
    $rows = app(McpConfigSnapshot::class)->doctor();

    expect($rows)->not->toBeEmpty();

    foreach ($rows as $row) {
        expect($row)->toHaveKeys(['level', 'label', 'detail'])
            ->and($row['level'])->toBeIn(['ok', 'warn', 'fail']);
    }

    expect(collect($rows)->pluck('label'))->toContain('laravel/sanctum installed', 'Tools registered');
});

it('lists the registered tools and flags the package-gated ones', function () {
    $tools = app(McpConfigSnapshot::class)->tools();

    expect(collect($tools)->pluck('name'))->toContain('WhoAmITool');

    // sanctum is installed in the suite, so the token tools register and are gated.
    $issue = collect($tools)->firstWhere('name', 'IssueMcpTokenTool');
    expect($issue)->not->toBeNull()
        ->and($issue['gated'])->toBeTrue();
});

it('returns the configured ability map', function () {
    expect(app(McpConfigSnapshot::class)->abilities())
        ->toHaveKey('manage-mcp')
        ->and(app(McpConfigSnapshot::class)->abilities()['manage-mcp'])->toBe('mcp-kit.manage-mcp');
});

it('runs the core health checks plus app-defined ones', function () {
    app(HealthRegistry::class)->register('demo', fn () => true);

    $result = app(SystemHealth::class)->run();

    expect($result)->toHaveKeys(['healthy', 'checked_at', 'checks'])
        ->and($result['checks'])->toHaveKeys(['database', 'cache', 'queue', 'storage', 'app:demo'])
        ->and($result['checks']['app:demo']['healthy'])->toBeTrue();
});
