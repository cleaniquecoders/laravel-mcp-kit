<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * List the failed jobs from Laravel's failed-jobs store (`failed_jobs` is core
 * Laravel). Identified by their public uuid, never an internal id, and paged.
 * Pair with retry_failed_job to act on one.
 */
#[Name('list_failed_jobs')]
#[Description('List failed queue jobs (id, uuid, connection, queue, job name, error, failed_at), optionally filtered by connection or queue. Paginated. Pass the `id` to retry_failed_job.')]
#[IsReadOnly]
class ListFailedJobsTool extends McpKitTool
{
    protected function ability(): string
    {
        return $this->configuredAbility('view-jobs');
    }

    public function handle(Request $request): Response
    {
        if ($this->authorizedUser($request) === null) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'connection' => ['nullable', 'string', 'max:255'],
            'queue' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        /** @var FailedJobProviderInterface $failer */
        $failer = app('queue.failer');

        $jobs = collect($failer->all())
            ->when($validated['connection'] ?? null, fn ($c, $v) => $c->where('connection', $v))
            ->when($validated['queue'] ?? null, fn ($c, $v) => $c->where('queue', $v))
            ->values();

        $perPage = 20;
        $page = (int) ($validated['page'] ?? 1);

        $paginator = new LengthAwarePaginator(
            $jobs->forPage($page, $perPage)->values(),
            $jobs->count(),
            $perPage,
            $page,
        );

        return Response::json(
            $this->paginatedSummary($paginator, $this->summary(...))
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(object $job): array
    {
        $payload = json_decode((string) ($job->payload ?? ''), true);
        $name = is_array($payload) ? ($payload['displayName'] ?? null) : null;

        // `id` is the failer's own handle — the value retry_failed_job needs
        // for find()/forget(). On the default `database-uuids` driver it IS a
        // uuid; on the legacy `database`/dynamodb drivers it is the row's
        // integer key. `uuid` is the job's logical uuid, always present in the
        // payload, for correlation across both — so we never mislabel an int.
        $logicalUuid = is_array($payload) ? ($payload['uuid'] ?? null) : null;

        $exception = (string) ($job->exception ?? '');
        $firstLine = Str::of($exception)->explode("\n")->first() ?: null;

        return [
            'id' => (string) ($job->id ?? ''),
            'uuid' => $logicalUuid,
            'connection' => $job->connection ?? null,
            'queue' => $job->queue ?? null,
            'name' => $name,
            'error' => $firstLine !== null ? Str::limit($firstLine, 240) : null,
            'failed_at' => isset($job->failed_at) ? (string) $job->failed_at : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'connection' => $schema->string()
                ->description('Filter by the queue connection name.'),
            'queue' => $schema->string()
                ->description('Filter by the queue name.'),
            'page' => $schema->integer()
                ->description('Page number, starting at 1.'),
        ];
    }
}
