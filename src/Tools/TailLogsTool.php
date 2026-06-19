<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use CleaniqueCoders\LaravelMcpKit\Tools\Concerns\InteractsWithLogs;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

/**
 * Read the tail of a log file — the agent equivalent of `tail -n`. Returns
 * whole log entries (a stack trace stays attached to its header), newest last,
 * optionally filtered by level.
 */
#[Name('tail_logs')]
#[Description('Return the most recent entries from a log file in storage/logs, optionally filtered by level. Defaults to the newest log file.')]
#[IsReadOnly]
class TailLogsTool extends McpKitTool
{
    use InteractsWithLogs;

    protected function ability(): string
    {
        return $this->configuredAbility('view-logs');
    }

    public function handle(Request $request): Response
    {
        if ($this->authorizedUser($request) === null) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'file' => ['nullable', 'string', 'max:255'],
            'lines' => ['nullable', 'integer', 'min:1', 'max:'.$this->maxLogLines()],
            'level' => ['nullable', 'string', 'in:'.implode(',', $this->logLevels())],
        ]);

        try {
            $path = $this->resolveLogFile($validated['file'] ?? null);
        } catch (Throwable $e) {
            return Response::error($e->getMessage());
        }

        if ($path === null) {
            return Response::json([
                'file' => null,
                'entries' => [],
                'available_files' => array_keys($this->logFiles()),
                'message' => 'No log files found in the log directory.',
            ]);
        }

        $lines = (int) ($validated['lines'] ?? 100);

        $tail = $this->readLogTail($path);
        $entries = $this->parseLogEntries($tail['contents']);
        $entries = $this->filterLogEntries($entries, $validated['level'] ?? null, null);

        $entries = array_slice($entries, -$lines);

        return Response::json([
            'file' => basename($path),
            'truncated' => $tail['truncated'],
            'count' => count($entries),
            'entries' => array_map($this->presentEntry(...), $entries),
            'available_files' => array_keys($this->logFiles()),
        ]);
    }

    /**
     * @param  array{timestamp: string, channel: string, level: string, message: string, text: string}  $entry
     * @return array<string, string>
     */
    protected function presentEntry(array $entry): array
    {
        return [
            'timestamp' => $entry['timestamp'],
            'channel' => $entry['channel'],
            'level' => $entry['level'],
            'message' => $entry['text'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()
                ->description('Log file name (basename, e.g. laravel.log). Defaults to the newest file.'),
            'lines' => $schema->integer()
                ->description('How many recent entries to return (default 100).'),
            'level' => $schema->string()
                ->enum($this->logLevels())
                ->description('Only return entries at this log level.'),
        ];
    }
}
