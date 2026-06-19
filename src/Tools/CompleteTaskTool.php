<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use CleaniqueCoders\LaravelMcpKit\Actions\CompleteTask;
use CleaniqueCoders\LaravelMcpKit\Tools\Concerns\InteractsWithTasks;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('complete_task')]
#[Description('Mark a task as done by its uuid. Returns the updated task. This changes state — clients should confirm before calling.')]
class CompleteTaskTool extends McpKitTool
{
    use InteractsWithTasks;

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
        ]);

        $task = $this->resolveTask($request);

        if ($task === null) {
            return Response::error('No task found for that uuid.');
        }

        $task = (new CompleteTask($task))->handle();

        return Response::json([
            'message' => 'Task marked as done.',
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
                ->description('The uuid of the task to complete.')
                ->required(),
        ];
    }
}
