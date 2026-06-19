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
 * Bundle a filtered slice of a log file and hand back a short-lived signed
 * download URL — never the raw text inlined into the response. The whole point
 * of the download() base helper: large or sensitive artifacts leave through a
 * signed, expiring URL a human can click.
 *
 * Annotated read-only: it reads logs. The export file it writes is a transient
 * artifact, not application state.
 */
#[Name('export_logs')]
#[Description('Export a filtered slice of a log file and return a short-lived signed download URL (not the raw text). Filter by file, level and free text.')]
#[IsReadOnly]
class ExportLogsTool extends McpKitTool
{
    use InteractsWithLogs;

    protected function ability(): string
    {
        return $this->configuredAbility('export-logs');
    }

    public function handle(Request $request): Response
    {
        if ($this->authorizedUser($request) === null) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'file' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'in:'.implode(',', $this->logLevels())],
            'query' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $path = $this->resolveLogFile($validated['file'] ?? null);
        } catch (Throwable $e) {
            return Response::error($e->getMessage());
        }

        if ($path === null) {
            return Response::error('No log files found to export.');
        }

        $read = $this->readLogTail($path);
        $entries = $this->filterLogEntries(
            $this->parseLogEntries($read['contents']),
            $validated['level'] ?? null,
            $validated['query'] ?? null,
        );

        if ($entries === []) {
            return Response::error('No log entries matched the given filters — nothing to export.');
        }

        $body = implode("\n", array_map(fn (array $e): string => $e['text'], $entries))."\n";
        $filename = pathinfo(basename($path), PATHINFO_FILENAME).'-export.log';

        try {
            $url = $this->download($body, $filename);
        } catch (Throwable $e) {
            return Response::error('Failed to write the export: '.$e->getMessage());
        }

        return Response::json([
            'message' => 'Export ready. The link is signed and expires shortly.',
            'file' => basename($path),
            'entries' => count($entries),
            'truncated' => $read['truncated'],
            'download_url' => $url,
            'expires_in_minutes' => (int) config('mcp-kit.ops.export.ttl', 15),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()
                ->description('Log file to export (basename). Defaults to the newest file.'),
            'level' => $schema->string()
                ->enum($this->logLevels())
                ->description('Only export entries at this level.'),
            'query' => $schema->string()
                ->description('Only export entries matching this free text.'),
        ];
    }
}
