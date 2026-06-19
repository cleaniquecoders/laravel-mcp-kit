<?php

namespace CleaniqueCoders\LaravelMcpKit\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Write export contents to a Storage disk and return a short-lived signed
 * download URL.
 *
 * This is the reusable spine behind `export_logs` and any host
 * `export_*_report` tool: an agent should never receive a megabyte of log
 * text inlined in a tool result — it gets a URL a human can click, valid for
 * a few minutes, served by the signed `mcp-kit.download` route.
 *
 * The plain signed-route path is the default and works on any disk. Hosts that
 * use `cleaniquecoders/laravel-media-secure` can layer their own access policy
 * on top by pointing the export disk at a secured one — see docs.
 */
class ExportToSignedUrl
{
    public function __construct(
        protected string $contents,
        protected string $filename,
        protected ?string $disk = null,
        protected ?int $ttlMinutes = null,
    ) {}

    public function handle(): string
    {
        $disk = $this->disk ?? (string) config('mcp-kit.ops.export.disk', 'local');
        $directory = trim((string) config('mcp-kit.ops.export.directory', 'mcp-kit/exports'), '/');
        $ttl = $this->ttlMinutes ?? (int) config('mcp-kit.ops.export.ttl', 15);

        $filename = $this->safeFilename($this->filename);
        $path = $directory.'/'.$filename;

        Storage::disk($disk)->put($path, $this->contents);

        return URL::temporarySignedRoute(
            'mcp-kit.download',
            now()->addMinutes($ttl),
            ['file' => $filename],
        );
    }

    /**
     * Reject path traversal and keep the stored name to a known-safe charset,
     * preserving the extension. A random prefix avoids collisions between
     * concurrent exports of the same logical file.
     */
    protected function safeFilename(string $filename): string
    {
        $base = basename($filename);

        if ($base === '' || $base === '.' || $base === '..') {
            throw new InvalidArgumentException("Invalid export filename [{$filename}].");
        }

        $extension = pathinfo($base, PATHINFO_EXTENSION);
        $name = Str::slug(pathinfo($base, PATHINFO_FILENAME)) ?: 'export';
        $name = Str::limit($name, 80, '');

        return Str::lower(Str::random(8)).'-'.$name.($extension !== '' ? '.'.Str::slug($extension) : '');
    }
}
