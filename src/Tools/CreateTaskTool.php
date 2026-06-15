<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use CleaniqueCoders\LaravelMcpKit\Actions\CreateTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * A WRITE tool. Note there is no #[IsReadOnly] annotation — MCP clients
 * surface that to the user as "this tool changes state", which is what
 * drives the human-in-the-loop approval gate. Write tools must always be
 * honestly annotated.
 */
#[Name('create_task')]
#[Description('Create a new task in "open" status. Returns the created task. This changes state — clients should confirm before calling.')]
class CreateTaskTool extends McpKitTool
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

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee' => ['nullable', 'string', 'max:255'],
        ]);

        // Funnel through the same Action the web UI and CLI use.
        $task = (new CreateTask($validated))->handle();

        return Response::json([
            'message' => 'Task created.',
            'task' => $this->taskSummary($task),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('Short title of the task.')
                ->required(),
            'description' => $schema->string()
                ->description('Optional longer description.'),
            'assignee' => $schema->string()
                ->description('Optional username to assign the task to.'),
        ];
    }
}
