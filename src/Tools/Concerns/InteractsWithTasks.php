<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools\Concerns;

use CleaniqueCoders\LaravelMcpKit\Models\Task;
use Laravel\Mcp\Request;

/**
 * Task-domain helpers for the reference task tools.
 *
 * These live in a trait, not the McpKitTool base, on purpose: the base is the
 * generic, copyable pattern (gate-first auth, uuid-only output, pagination,
 * downloads) and must stay free of any one domain. A real tool you add for
 * your own domain would write its own `*Summary()` helper the same way.
 */
trait InteractsWithTasks
{
    /**
     * Resolve a task by its public uuid (the `task` input).
     */
    protected function resolveTask(Request $request): ?Task
    {
        $uuid = $request->get('task');

        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return Task::query()->where('uuid', $uuid)->first();
    }

    /**
     * Compact, uuid-only task payload for responses.
     *
     * @return array<string, mixed>
     */
    protected function taskSummary(Task $task): array
    {
        return [
            'uuid' => $task->uuid,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
            'assignee' => $task->assignee,
            'created_at' => $task->created_at?->toIso8601String(),
        ];
    }
}
