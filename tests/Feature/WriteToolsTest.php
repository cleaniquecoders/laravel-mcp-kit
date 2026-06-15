<?php

use CleaniqueCoders\LaravelMcpKit\Enums\TaskStatus;
use CleaniqueCoders\LaravelMcpKit\Models\Task;
use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;
use CleaniqueCoders\LaravelMcpKit\Tools\CompleteTaskTool;
use CleaniqueCoders\LaravelMcpKit\Tools\CreateTaskTool;

it('lets a manager create a task', function () {
    TaskServer::actingAs(manager())
        ->tool(CreateTaskTool::class, ['title' => 'Ship the docs'])
        ->assertOk()
        ->assertSee('Task created');

    $task = Task::query()->firstWhere('title', 'Ship the docs');

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(TaskStatus::Open);
});

it('lets a manager complete a task', function () {
    $task = Task::factory()->open()->create();

    TaskServer::actingAs(manager())
        ->tool(CompleteTaskTool::class, ['task' => $task->uuid])
        ->assertOk()
        ->assertSee('done');

    expect($task->fresh()->status)->toBe(TaskStatus::Done);
});

it('errors when completing an unknown task uuid', function () {
    TaskServer::actingAs(manager())
        ->tool(CompleteTaskTool::class, ['task' => 'not-a-real-uuid'])
        ->assertHasErrors();
});

it('blocks a viewer from creating a task (the human gate is an ability, not a hint)', function () {
    TaskServer::actingAs(viewer())
        ->tool(CreateTaskTool::class, ['title' => 'Should not exist'])
        ->assertHasErrors();

    expect(Task::query()->where('title', 'Should not exist')->exists())->toBeFalse();
});

it('blocks a viewer from completing a task', function () {
    $task = Task::factory()->open()->create();

    TaskServer::actingAs(viewer())
        ->tool(CompleteTaskTool::class, ['task' => $task->uuid])
        ->assertHasErrors();

    expect($task->fresh()->status)->toBe(TaskStatus::Open);
});
