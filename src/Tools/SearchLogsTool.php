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
 * Search log entries by free text and/or level across one file (or all files
 * in the log directory). Returns whole matching entries, newest first.
 */
#[Name('search_logs')]
#[Description('Search storage/logs entries by free text and/or level, across one file or all log files. Returns whole matching entries.')]
#[IsReadOnly]
class SearchLogsTool extends McpKitTool
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
            'query' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'in:'.implode(',', $this->logLevels())],
            'file' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$this->maxLogLines()],
        ]);

        $query = $validated['query'] ?? null;
        $level = $validated['level'] ?? null;

        if (($query === null || $query === '') && ($level === null || $level === '')) {
            return Response::error('Provide at least a `query` or a `level` to search for.');
        }

        try {
            $files = $this->filesToSearch($validated['file'] ?? null);
        } catch (Throwable $e) {
            return Response::error($e->getMessage());
        }

        $limit = (int) ($validated['limit'] ?? 50);
        $matches = [];
        $truncated = false;

        foreach ($files as $name => $path) {
            $read = $this->readLogTail($path);
            $truncated = $truncated || $read['truncated'];

            $entries = $this->filterLogEntries($this->parseLogEntries($read['contents']), $level, $query);

            foreach ($entries as $entry) {
                $matches[] = [
                    'file' => $name,
                    'timestamp' => $entry['timestamp'],
                    'channel' => $entry['channel'],
                    'level' => $entry['level'],
                    'message' => $entry['text'],
                ];
            }
        }

        // Newest first, then cap.
        usort($matches, fn (array $a, array $b): int => strcmp($b['timestamp'], $a['timestamp']));
        $total = count($matches);
        $matches = array_slice($matches, 0, $limit);

        return Response::json([
            'query' => $query,
            'level' => $level !== null ? strtoupper($level) : null,
            'files_searched' => array_keys($files),
            'truncated' => $truncated,
            'returned' => count($matches),
            'total_matches' => $total,
            'entries' => $matches,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function filesToSearch(?string $file): array
    {
        if ($file !== null && $file !== '') {
            $path = $this->resolveLogFile($file);

            return $path === null ? [] : [basename($path) => $path];
        }

        return $this->logFiles();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Free text to match within a log entry (case-insensitive).'),
            'level' => $schema->string()
                ->enum($this->logLevels())
                ->description('Only match entries at this log level.'),
            'file' => $schema->string()
                ->description('Restrict the search to one log file (basename). Defaults to all files.'),
            'limit' => $schema->integer()
                ->description('Maximum entries to return (default 50).'),
        ];
    }
}
