<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools\Concerns;

use InvalidArgumentException;

/**
 * Shared log-reading helpers for the generic log tools (tail / search /
 * export). Keeps file resolution safe (no traversal), parses Laravel log
 * lines into whole entries (so a stack trace stays attached to its header),
 * and bounds how much of a file is ever read into memory.
 */
trait InteractsWithLogs
{
    /**
     * Hard cap on bytes read from the end of any single log file, so a
     * multi-gigabyte log can never exhaust memory.
     */
    protected int $maxLogBytes = 5_242_880; // 5 MB

    protected function logDirectory(): string
    {
        return (string) config('mcp-kit.ops.logs.path', storage_path('logs'));
    }

    protected function maxLogLines(): int
    {
        return (int) config('mcp-kit.ops.logs.max_lines', 500);
    }

    /**
     * Log files in the directory, newest first, keyed by basename.
     *
     * @return array<string, string>
     */
    protected function logFiles(): array
    {
        $directory = $this->logDirectory();

        if (! is_dir($directory)) {
            return [];
        }

        $files = glob($directory.'/*.log') ?: [];

        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        $keyed = [];

        foreach ($files as $file) {
            $keyed[basename($file)] = $file;
        }

        return $keyed;
    }

    /**
     * Resolve a requested log file to an absolute path, refusing anything
     * outside the log directory. Null name => the newest log file.
     */
    protected function resolveLogFile(?string $name): ?string
    {
        $files = $this->logFiles();

        if ($files === []) {
            return null;
        }

        if ($name === null || $name === '') {
            return reset($files);
        }

        $name = basename($name);

        if (! isset($files[$name])) {
            throw new InvalidArgumentException("No log file named [{$name}] in the log directory.");
        }

        return $files[$name];
    }

    /**
     * Read up to {@see $maxLogBytes} from the END of a file. Returns the text
     * and whether it was truncated (so callers can say so honestly).
     *
     * @return array{contents: string, truncated: bool}
     */
    protected function readLogTail(string $path): array
    {
        $size = filesize($path);

        if ($size === false || $size === 0) {
            return ['contents' => '', 'truncated' => false];
        }

        $truncated = $size > $this->maxLogBytes;
        $offset = $truncated ? $size - $this->maxLogBytes : 0;

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return ['contents' => '', 'truncated' => false];
        }

        if ($offset > 0) {
            fseek($handle, $offset);
            fgets($handle); // drop the partial first line
        }

        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return ['contents' => $contents, 'truncated' => $truncated];
    }

    /**
     * Parse Laravel log text into whole entries. A line beginning with a
     * timestamp header starts a new entry; everything after (stack traces,
     * context) is appended to that entry's text.
     *
     * @return array<int, array{timestamp: string, channel: string, level: string, message: string, text: string}>
     */
    protected function parseLogEntries(string $contents): array
    {
        $lines = preg_split('/\R/', $contents) ?: [];

        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\]\s*([\w.-]+)\.(\w+):\s?(.*)$/', $line, $m) === 1) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $current = [
                    'timestamp' => $m[1],
                    'channel' => $m[2],
                    'level' => strtoupper($m[3]),
                    'message' => $m[4],
                    'text' => $line,
                ];

                continue;
            }

            if ($current !== null) {
                $current['text'] .= "\n".$line;
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return $entries;
    }

    /**
     * @param  array<int, array{timestamp: string, channel: string, level: string, message: string, text: string}>  $entries
     * @return array<int, array{timestamp: string, channel: string, level: string, message: string, text: string}>
     */
    protected function filterLogEntries(array $entries, ?string $level, ?string $query): array
    {
        $level = $level !== null && $level !== '' ? strtoupper($level) : null;
        $query = $query !== null && $query !== '' ? $query : null;

        return array_values(array_filter($entries, function (array $entry) use ($level, $query): bool {
            if ($level !== null && $entry['level'] !== $level) {
                return false;
            }

            if ($query !== null && stripos($entry['text'], $query) === false) {
                return false;
            }

            return true;
        }));
    }

    /**
     * The Monolog/PSR-3 levels, for schema enums and validation.
     *
     * @return array<int, string>
     */
    protected function logLevels(): array
    {
        return ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
    }
}
