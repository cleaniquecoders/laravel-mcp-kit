<?php

use CleaniqueCoders\LaravelMcpKit\Prompts\SupportRunbookPrompt;
use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;
use CleaniqueCoders\LaravelMcpKit\Servers\ToolRegistry;

it('is registered as a prompt', function () {
    expect(ToolRegistry::prompts())->toContain(SupportRunbookPrompt::class);
});

it('renders a read-first, human-gated runbook', function () {
    TaskServer::prompt(SupportRunbookPrompt::class)
        ->assertOk()
        ->assertSee('whoami')
        ->assertSee('human gate');
});

it('weaves the symptom into the runbook when given', function () {
    TaskServer::prompt(SupportRunbookPrompt::class, ['symptom' => 'queue is backing up'])
        ->assertOk()
        ->assertSee('queue is backing up');
});
