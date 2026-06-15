<?php

namespace CleaniqueCoders\LaravelMcpKit\Actions;

use CleaniqueCoders\LaravelMcpKit\Enums\TaskStatus;
use CleaniqueCoders\LaravelMcpKit\Models\Task;

/**
 * The single source of truth for creating a task.
 *
 * The MCP write tool calls THIS — it never touches the model directly.
 * That is the whole point of the action pattern in an MCP context: the
 * agent, the web UI, and the CLI all funnel through the same business
 * rules, so MCP can never become a back door around them.
 */
class CreateTask
{
    /**
     * @param  array{title: string, description?: string|null, assignee?: string|null}  $attributes
     */
    public function __construct(protected array $attributes) {}

    public function handle(): Task
    {
        return Task::create([
            'title' => $this->attributes['title'],
            'description' => $this->attributes['description'] ?? null,
            'assignee' => $this->attributes['assignee'] ?? null,
            'status' => TaskStatus::Open,
        ]);
    }
}
