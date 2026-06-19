<?php

use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;
use CleaniqueCoders\LaravelMcpKit\Support\HealthRegistry;
use CleaniqueCoders\LaravelMcpKit\Tools\SystemHealthTool;
use Laravel\Mcp\Facades\Mcp;

it('reports the core infrastructure checks', function () {
    TaskServer::actingAs(granted(['view-system']))
        ->tool(SystemHealthTool::class)
        ->assertOk()
        ->assertSee('"database"')
        ->assertSee('"cache"')
        ->assertSee('"queue"')
        ->assertSee('"storage"');
});

it('blocks a user without the view-system ability', function () {
    TaskServer::actingAs(nobody())
        ->tool(SystemHealthTool::class)
        ->assertHasErrors();
});

it('includes app-defined checks registered via Mcp::healthCheck', function () {
    Mcp::healthCheck('billing_api', fn () => true);

    TaskServer::actingAs(admin())
        ->tool(SystemHealthTool::class)
        ->assertOk()
        ->assertSee('app:billing_api');
});

it('marks the overall result unhealthy when an app check throws', function () {
    app(HealthRegistry::class)->register('flaky', function () {
        throw new RuntimeException('down');
    });

    TaskServer::actingAs(admin())
        ->tool(SystemHealthTool::class)
        ->assertOk()
        ->assertSee('"healthy":false')
        ->assertSee('down');
});
