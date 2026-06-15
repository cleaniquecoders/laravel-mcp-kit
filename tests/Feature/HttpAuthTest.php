<?php

use CleaniqueCoders\LaravelMcpKit\Models\Task;
use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;
use CleaniqueCoders\LaravelMcpKit\Tests\Fixtures\User;
use CleaniqueCoders\LaravelMcpKit\Tools\GetTaskTool;

it('rejects the HTTP endpoint without a token', function () {
    $this->postJson(route('mcp-kit.tasks'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
        ->assertUnauthorized();
});

it('lets a valid Sanctum token through the auth guard', function () {
    $user = User::create(['email' => 'aisyah@example.test', 'grants' => ['view', 'manage']]);
    $token = $user->createToken('mcp-kit')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('mcp-kit.tasks'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);

    // The guard let us past — the 401 boundary is what we are asserting here.
    expect($response->status())->not->toBe(401);
});

it('never exposes the internal integer id in a tool response', function () {
    $task = Task::factory()->open()->create();

    TaskServer::actingAs(manager())
        ->tool(GetTaskTool::class, ['task' => $task->uuid])
        ->assertOk()
        ->assertSee($task->uuid)
        ->assertDontSee('"id"');
});
