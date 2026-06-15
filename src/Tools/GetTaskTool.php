<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_task')]
#[Description('Fetch a single task by its uuid, including its full description and status.')]
#[IsReadOnly]
class GetTaskTool extends McpKitTool
{
    protected function ability(): string
    {
        return 'mcp-kit.view-tasks';
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

        return Response::json(['task' => $this->taskSummary($task)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task' => $schema->string()
                ->description('The uuid of the task (see list_tasks).')
                ->required(),
        ];
    }
}
