<?php

use CleaniqueCoders\LaravelMcpKit\Models\Task;
use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;
use CleaniqueCoders\LaravelMcpKit\Tools\ListTasksTool;

it('lists tasks for a viewer', function () {
    Task::factory()->create(['title' => 'Alpha task']);

    TaskServer::actingAs(viewer())
        ->tool(ListTasksTool::class)
        ->assertOk()
        ->assertSee('Alpha task');
});

it('exposes the uuid but never the internal id', function () {
    $task = Task::factory()->create();

    $response = TaskServer::actingAs(viewer())->tool(ListTasksTool::class);

    $response->assertOk()->assertSee($task->uuid);
    // The auto-increment id must not leak into the payload.
    $response->assertDontSee('"id"');
});

it('filters tasks by status', function () {
    Task::factory()->open()->create(['title' => 'Still open']);
    Task::factory()->done()->create(['title' => 'Already done']);

    TaskServer::actingAs(viewer())
        ->tool(ListTasksTool::class, ['status' => 'open'])
        ->assertOk()
        ->assertSee('Still open')
        ->assertDontSee('Already done');
});

it('rejects a user without the view ability', function () {
    TaskServer::actingAs(nobody())
        ->tool(ListTasksTool::class)
        ->assertHasErrors();
});
