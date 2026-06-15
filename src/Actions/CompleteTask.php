<?php

namespace CleaniqueCoders\LaravelMcpKit\Actions;

use CleaniqueCoders\LaravelMcpKit\Enums\TaskStatus;
use CleaniqueCoders\LaravelMcpKit\Models\Task;

/**
 * Mark a task as done. Shared by the web UI, CLI, and the MCP tool.
 */
class CompleteTask
{
    public function __construct(protected Task $task) {}

    public function handle(): Task
    {
        $this->task->update(['status' => TaskStatus::Done]);

        return $this->task->fresh();
    }
}
