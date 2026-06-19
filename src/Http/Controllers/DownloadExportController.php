<?php

namespace CleaniqueCoders\LaravelMcpKit\Http\Controllers;

use CleaniqueCoders\LaravelMcpKit\Actions\ExportToSignedUrl;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves an export artifact behind the `signed` middleware.
 *
 * The capability IS the signature: {@see ExportToSignedUrl}
 * hands out a temporary signed URL, and Laravel's `signed` middleware (applied
 * on the route) rejects anything tampered or expired before this runs. We only
 * need to resolve the file safely and stream it.
 */
class DownloadExportController
{
    public function __invoke(string $file): StreamedResponse
    {
        // The signature covers the `file` value, but defend in depth: only ever
        // look inside the export directory, never up the tree.
        $file = basename($file);

        $disk = (string) config('mcp-kit.ops.export.disk', 'local');
        $directory = trim((string) config('mcp-kit.ops.export.directory', 'mcp-kit/exports'), '/');
        $path = $directory.'/'.$file;

        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, $file);
    }
}
