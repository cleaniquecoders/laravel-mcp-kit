<?php

namespace CleaniqueCoders\LaravelMcpKit\Actions;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Queue\Events\JobRetryRequested;
use Illuminate\Queue\Failed\FailedJobProviderInterface;

/**
 * Re-dispatch a failed job back onto its original connection and queue, then
 * remove it from the failed-jobs store — funnelled through an Action so the MCP
 * write tool, a CLI, or a web button all share one path (MCP is never a back
 * door around your business rules).
 *
 * Follows Illuminate\Queue\Console\RetryCommand::handle(): fire JobRetryRequested
 * (so Horizon and host listeners track the retry), reset the attempt counter so
 * the job runs fresh, push the raw payload back, then forget it. It deliberately
 * does NOT re-resolve the job to refresh a retryUntil() deadline or copy
 * connection-level queueable options — keep it to the common path; reach for
 * `queue:retry` for those.
 */
class RetryFailedJob
{
    public function __construct(protected string $id) {}

    /**
     * @return bool true when a failed job was found and retried.
     */
    public function handle(): bool
    {
        /** @var FailedJobProviderInterface $failer */
        $failer = app('queue.failer');

        $job = $failer->find($this->id);

        if ($job === null) {
            return false;
        }

        app('events')->dispatch(new JobRetryRequested($job));

        app(QueueFactory::class)
            ->connection($job->connection)
            ->pushRaw($this->resetAttempts($job->payload), $job->queue);

        $failer->forget($this->id);

        return true;
    }

    /**
     * Zero the attempt counter so the retried job starts from a clean slate.
     */
    protected function resetAttempts(string $payload): string
    {
        $decoded = json_decode($payload, true);

        if (is_array($decoded) && isset($decoded['attempts'])) {
            $decoded['attempts'] = 0;

            return (string) json_encode($decoded);
        }

        return $payload;
    }
}
