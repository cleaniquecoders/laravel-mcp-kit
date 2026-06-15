<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use CleaniqueCoders\LaravelMcpKit\Models\Task;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base class for every MCP Kit tool.
 *
 * Two production lessons are baked in here:
 *
 *  1. Authorization is per-tool, checked on the token holder. MCP is a
 *     third UI on top of the same Gate abilities the web app uses — it
 *     must never be a back door. A tool the user can't perform returns
 *     an error, not partial data.
 *
 *  2. Inputs and outputs speak `uuid` only. The internal auto-increment
 *     id is never exposed to the agent.
 *
 * The abilities are defined by the HOST application (see the README), so
 * the package stays framework-native and does not force a permission
 * package on you.
 */
abstract class McpKitTool extends Tool
{
    /**
     * The Gate ability required to use this tool, e.g. `mcp-kit.view-tasks`.
     */
    abstract protected function ability(): string;

    /**
     * Return the authenticated user when they hold this tool's ability,
     * or null otherwise.
     */
    protected function authorizedUser(Request $request): ?Authenticatable
    {
        $user = $request->user();

        if ($user === null || ! $user->can($this->ability())) {
            return null;
        }

        return $user;
    }

    protected function unauthorized(): Response
    {
        return Response::error(
            "Unauthorized — this tool requires the '{$this->ability()}' ability."
        );
    }

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
