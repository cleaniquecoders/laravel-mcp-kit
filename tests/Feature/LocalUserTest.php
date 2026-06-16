<?php

use CleaniqueCoders\LaravelMcpKit\Models\Task;
use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;
use CleaniqueCoders\LaravelMcpKit\Tests\Fixtures\User;
use CleaniqueCoders\LaravelMcpKit\Tools\ListTasksTool;

it('acts as the configured local user over stdio (no request user)', function () {
    Task::factory()->create(['title' => 'Stdio task']);
    User::create(['email' => 'local@example.test', 'grants' => ['view']]);
    config(['mcp-kit.local.user' => 'local@example.test']);

    // No actingAs() — the stdio transport has no authenticated request user,
    // so the tool falls back to the configured local user.
    TaskServer::tool(ListTasksTool::class)
        ->assertOk()
        ->assertSee('Stdio task');
});

it('stays unauthorized over stdio when no local user is configured', function () {
    config(['mcp-kit.local.user' => null]);

    TaskServer::tool(ListTasksTool::class)->assertHasErrors();
});

it('stays unauthorized when the configured local user lacks the ability', function () {
    User::create(['email' => 'reader@example.test', 'grants' => []]);
    config(['mcp-kit.local.user' => 'reader@example.test']);

    TaskServer::tool(ListTasksTool::class)->assertHasErrors();
});
