<?php

use CleaniqueCoders\LaravelMcpKit\Models\Task;
use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;
use CleaniqueCoders\LaravelMcpKit\Tools\AssignTaskTool;

it('lets a manager assign a task', function () {
    $task = Task::factory()->create(['assignee' => null]);

    TaskServer::actingAs(manager())
        ->tool(AssignTaskTool::class, ['task' => $task->uuid, 'assignee' => 'farid'])
        ->assertOk()
        ->assertSee('assigned to farid');

    expect($task->fresh()->assignee)->toBe('farid');
});

it('clears the assignee when none is given', function () {
    $task = Task::factory()->create(['assignee' => 'aisyah']);

    TaskServer::actingAs(manager())
        ->tool(AssignTaskTool::class, ['task' => $task->uuid])
        ->assertOk()
        ->assertSee('unassigned');

    expect($task->fresh()->assignee)->toBeNull();
});

it('errors when assigning an unknown task uuid', function () {
    TaskServer::actingAs(manager())
        ->tool(AssignTaskTool::class, ['task' => 'not-a-real-uuid', 'assignee' => 'farid'])
        ->assertHasErrors();
});

it('blocks a viewer from assigning a task', function () {
    $task = Task::factory()->create(['assignee' => null]);

    TaskServer::actingAs(viewer())
        ->tool(AssignTaskTool::class, ['task' => $task->uuid, 'assignee' => 'farid'])
        ->assertHasErrors();

    expect($task->fresh()->assignee)->toBeNull();
});
