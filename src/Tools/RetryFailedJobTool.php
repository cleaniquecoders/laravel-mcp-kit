<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use CleaniqueCoders\LaravelMcpKit\Actions\RetryFailedJob;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Throwable;

/**
 * A WRITE tool — note the absence of #[IsReadOnly]. It re-dispatches a failed
 * job back onto its queue, which changes state, so clients surface it as such
 * and gate it behind human approval. The work funnels through the
 * RetryFailedJob Action (the same path a CLI or web button would use).
 */
#[Name('retry_failed_job')]
#[Description('Re-dispatch a failed queue job back onto its queue by its id (from list_failed_jobs). This changes state — clients should confirm before calling.')]
class RetryFailedJobTool extends McpKitTool
{
    protected function ability(): string
    {
        return $this->configuredAbility('manage-jobs');
    }

    public function handle(Request $request): Response
    {
        if ($this->authorizedUser($request) === null) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'id' => ['required', 'string', 'max:255'],
        ]);

        try {
            $retried = (new RetryFailedJob($validated['id']))->handle();
        } catch (Throwable $e) {
            return Response::error('Failed to retry the job: '.$e->getMessage());
        }

        if (! $retried) {
            return Response::error('No failed job found for that uuid.');
        }

        return Response::json([
            'message' => 'Job re-dispatched onto its queue.',
            'uuid' => $validated['id'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The id of the failed job (the `id` field from list_failed_jobs).')
                ->required(),
        ];
    }
}
