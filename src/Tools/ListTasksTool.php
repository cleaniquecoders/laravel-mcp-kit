<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use CleaniqueCoders\LaravelMcpKit\Enums\TaskStatus;
use CleaniqueCoders\LaravelMcpKit\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_tasks')]
#[Description('List tasks, optionally filtered by status or assignee, with free-text search across the title. Paginated (20 per page).')]
#[IsReadOnly]
class ListTasksTool extends McpKitTool
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

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', TaskStatus::values())],
            'assignee' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tasks = Task::query()
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($validated['assignee'] ?? null, fn ($q, $a) => $q->where('assignee', $a))
            ->when($validated['search'] ?? null, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest()
            ->paginate(perPage: 20, page: (int) ($validated['page'] ?? 1));

        return Response::json([
            'tasks' => $tasks->getCollection()->map(fn (Task $t) => $this->taskSummary($t))->all(),
            'pagination' => [
                'page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(TaskStatus::values())
                ->description('Filter by task status.'),
            'assignee' => $schema->string()
                ->description('Filter by exact assignee username.'),
            'search' => $schema->string()
                ->description('Free-text search across the task title.'),
            'page' => $schema->integer()
                ->description('Page number, starting at 1.'),
        ];
    }
}
