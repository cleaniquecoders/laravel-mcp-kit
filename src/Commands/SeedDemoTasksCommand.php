<?php

namespace CleaniqueCoders\LaravelMcpKit\Commands;

use CleaniqueCoders\LaravelMcpKit\Enums\TaskStatus;
use CleaniqueCoders\LaravelMcpKit\Models\Task;
use Illuminate\Console\Command;

/**
 * Seeds a handful of demo tasks so the MCP read tools have something to
 * return. Idempotent-ish: pass --fresh to wipe first.
 */
class SeedDemoTasksCommand extends Command
{
    protected $signature = 'mcp-kit:demo {--fresh : Delete existing tasks first}';

    protected $description = 'Seed demo tasks so the MCP read tools have something to return';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            Task::query()->delete();
            $this->warn('Existing tasks deleted.');
        }

        $tasks = [
            ['title' => 'Write onboarding docs', 'status' => TaskStatus::Open, 'assignee' => 'aisyah'],
            ['title' => 'Fix login redirect bug', 'status' => TaskStatus::InProgress, 'assignee' => 'farid'],
            ['title' => 'Review MCP server PR', 'status' => TaskStatus::Open, 'assignee' => 'farid'],
            ['title' => 'Ship v1.0 release notes', 'status' => TaskStatus::Done, 'assignee' => 'aisyah'],
            ['title' => 'Triage support inbox', 'status' => TaskStatus::Open, 'assignee' => null],
        ];

        foreach ($tasks as $attributes) {
            Task::create($attributes);
        }

        $this->info('Seeded '.count($tasks).' demo tasks.');

        return self::SUCCESS;
    }
}
