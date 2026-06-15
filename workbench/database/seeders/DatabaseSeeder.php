<?php

namespace Workbench\Database\Seeders;

use CleaniqueCoders\LaravelMcpKit\Enums\TaskStatus;
use CleaniqueCoders\LaravelMcpKit\Models\Task;
use Illuminate\Database\Seeder;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->manager()->create([
            'name' => 'Demo Manager',
            'email' => 'manager@example.com',
        ]);

        User::factory()->create([
            'name' => 'Demo Viewer',
            'email' => 'viewer@example.com',
        ]);

        $tasks = [
            ['title' => 'Write onboarding docs', 'status' => TaskStatus::Open, 'assignee' => 'aisyah'],
            ['title' => 'Fix login redirect bug', 'status' => TaskStatus::InProgress, 'assignee' => 'farid'],
            ['title' => 'Review MCP server PR', 'status' => TaskStatus::Open, 'assignee' => 'farid'],
            ['title' => 'Ship v1.0 release notes', 'status' => TaskStatus::Done, 'assignee' => 'aisyah'],
            ['title' => 'Triage support inbox', 'status' => TaskStatus::Open, 'assignee' => null],
        ];

        foreach ($tasks as $task) {
            Task::create($task);
        }
    }
}
