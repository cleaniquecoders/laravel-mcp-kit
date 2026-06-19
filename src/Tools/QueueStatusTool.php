<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

/**
 * A snapshot of queue depth: pending size on a connection/queue plus the
 * failed-jobs count. When Horizon is installed it's flagged as the source of
 * richer metrics (the Tier-2 enrichment hook).
 */
#[Name('queue_status')]
#[Description('Report queue depth — pending size for a connection/queue and the total failed-jobs count. Notes when Horizon is available for richer metrics.')]
#[IsReadOnly]
class QueueStatusTool extends McpKitTool
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
        ]);

        $explicitConnection = $validated['connection'] ?? null;
        $connectionName = $explicitConnection ?? config('queue.default');
        $queue = $validated['queue'] ?? null;

        $pending = null;
        $error = null;

        try {
            $connection = app('queue')->connection($explicitConnection);
            $pending = $connection->size($queue);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $failed = null;

        try {
            $failer = $this->failer();

            if ($failer instanceof CountableFailedJobProvider) {
                // The failed_jobs `connection` column records where a job RAN,
                // which is often not queue.default. So only scope the count when
                // the caller explicitly named a connection; otherwise report the
                // global total rather than silently under-reporting.
                $failed = $explicitConnection === null
                    ? $failer->count()
                    : $failer->count((string) $explicitConnection, $queue);
            } else {
                $failed = count($failer->all());
            }
        } catch (Throwable) {
            // leave null — failer may be the null provider
        }

        return Response::json([
            'connection' => $connectionName,
            'queue' => $queue ?? 'default',
            'pending' => $pending,
            'failed' => $failed,
            'horizon' => class_exists('Laravel\\Horizon\\Horizon'),
            'error' => $error,
        ]);
    }

    /**
     * Resolve the failed-job provider, typed as the interface so the
     * CountableFailedJobProvider narrowing below is meaningful (not all
     * providers — e.g. the null driver — can count).
     */
    protected function failer(): FailedJobProviderInterface
    {
        return app('queue.failer');
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'connection' => $schema->string()
                ->description('Queue connection to inspect (defaults to queue.default).'),
            'queue' => $schema->string()
                ->description('Queue name to size (defaults to the connection default).'),
        ];
    }
}
