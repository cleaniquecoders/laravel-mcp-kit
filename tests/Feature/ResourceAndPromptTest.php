<?php

use CleaniqueCoders\LaravelMcpKit\Models\Task;
use CleaniqueCoders\LaravelMcpKit\Prompts\TriageRunbookPrompt;
use CleaniqueCoders\LaravelMcpKit\Resources\TaskBoardResource;
use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;

it('reads the task board resource grouped by status', function () {
    Task::factory()->open()->create(['title' => 'Open one']);
    Task::factory()->done()->create(['title' => 'Done one']);

    TaskServer::actingAs(viewer())
        ->resource(TaskBoardResource::class)
        ->assertOk()
        ->assertSee('Open one')
        ->assertSee('Done one');
});

it('renders the triage runbook prompt with an assignee focus', function () {
    TaskServer::actingAs(viewer())
        ->prompt(TriageRunbookPrompt::class, ['assignee' => 'farid'])
        ->assertOk()
        ->assertSee('farid')
        ->assertSee('human-in-the-loop');
});

it('renders the triage runbook prompt without an assignee', function () {
    TaskServer::actingAs(viewer())
        ->prompt(TriageRunbookPrompt::class)
        ->assertOk()
        ->assertSee('all open tasks');
});
