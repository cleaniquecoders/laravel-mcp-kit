<?php

namespace CleaniqueCoders\LaravelMcpKit\Resources;

use CleaniqueCoders\LaravelMcpKit\Enums\TaskStatus;
use CleaniqueCoders\LaravelMcpKit\Models\Task;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

/**
 * A RESOURCE, not a tool.
 *
 * Resources expose read-only context an agent can pull by URI without
 * "doing" anything — here, a compact board grouped by status. The mental
 * model: a tool is a verb (an action), a resource is a noun (a document
 * the agent can read). Same auth rules still apply.
 */
#[Name('task_board')]
#[Title('Task Board')]
#[Description('A snapshot of all tasks grouped by status (open / in progress / done). Read-only context for grounding the agent.')]
#[Uri('mcp-kit://tasks/board')]
#[MimeType('application/json')]
class TaskBoardResource extends Resource
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof Authenticatable || ! $user->can('mcp-kit.view-tasks')) {
            return Response::error("Unauthorized — reading the task board requires the 'mcp-kit.view-tasks' ability.");
        }

        $board = [];

        foreach (TaskStatus::cases() as $status) {
            $board[$status->value] = Task::query()
                ->where('status', $status->value)
                ->latest()
                ->get()
                ->map(fn (Task $t) => ['uuid' => $t->uuid, 'title' => $t->title, 'assignee' => $t->assignee])
                ->all();
        }

        return Response::json([
            'generated_at' => now()->toIso8601String(),
            'board' => $board,
        ]);
    }
}
