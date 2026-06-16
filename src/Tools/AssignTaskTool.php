<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use CleaniqueCoders\LaravelMcpKit\Actions\AssignTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('assign_task')]
#[Description('Assign a task to someone (or clear it) by its uuid. Pass an empty assignee to unassign. Returns the updated task. This changes state — clients should confirm before calling.')]
class AssignTaskTool extends McpKitTool
{
    protected function ability(): string
    {
        return 'mcp-kit.manage-tasks';
    }

    public function handle(Request $request): Response
    {
        if ($this->authorizedUser($request) === null) {
            return $this->unauthorized();
        }

        $request->validate([
            'task' => ['required', 'string'],
            'assignee' => ['nullable', 'string', 'max:255'],
        ]);

        $task = $this->resolveTask($request);

        if ($task === null) {
            return Response::error('No task found for that uuid.');
        }

        $assignee = $request->get('assignee');
        $assignee = is_string($assignee) && $assignee !== '' ? $assignee : null;

        // Funnel through the same Action the web UI and CLI use.
        $task = (new AssignTask($task, $assignee))->handle();

        return Response::json([
            'message' => $assignee === null
                ? 'Task unassigned.'
                : "Task assigned to {$assignee}.",
            'task' => $this->taskSummary($task),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task' => $schema->string()
                ->description('The uuid of the task to assign.')
                ->required(),
            'assignee' => $schema->string()
                ->description('Username to assign the task to. Omit or pass empty to unassign.'),
        ];
    }
}
