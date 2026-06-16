<?php

namespace CleaniqueCoders\LaravelMcpKit\Actions;

use CleaniqueCoders\LaravelMcpKit\Models\Task;

/**
 * Assign (or unassign) a task. Shared by the web UI, CLI, and the MCP tool.
 *
 * Passing a null assignee clears the assignment — the agent, the web form,
 * and the console all funnel through this single rule.
 */
class AssignTask
{
    public function __construct(protected Task $task, protected ?string $assignee) {}

    public function handle(): Task
    {
        $this->task->update(['assignee' => $this->assignee]);

        return $this->task->fresh();
    }
}
