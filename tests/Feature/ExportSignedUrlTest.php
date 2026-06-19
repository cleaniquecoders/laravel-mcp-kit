<?php

use CleaniqueCoders\LaravelMcpKit\Actions\ExportToSignedUrl;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

it('registers the signed download route', function () {
    expect(Route::has('mcp-kit.download'))->toBeTrue();
});

it('writes an export and serves it through the signed url', function () {
    Storage::fake('local');

    $url = (new ExportToSignedUrl('hello from the export', 'report.log'))->handle();

    expect($url)->toContain('/mcp-kit/exports/');

    $response = $this->get($url);
    $response->assertOk();

    expect($response->streamedContent())->toContain('hello from the export');
});

it('rejects an unsigned or tampered download url with 403', function () {
    Storage::fake('local');

    $url = (new ExportToSignedUrl('secret', 'report.log'))->handle();

    // Tamper with the query string — the signature no longer matches.
    $this->get($url.'&x=1')->assertForbidden();

    // No signature at all.
    $this->get('/mcp-kit/exports/anything.log')->assertForbidden();
});

it('returns 404 when the signed file no longer exists', function () {
    Storage::fake('local');

    $url = (new ExportToSignedUrl('gone soon', 'report.log'))->handle();

    Storage::disk('local')->deleteDirectory('mcp-kit/exports');

    $this->get($url)->assertNotFound();
});

it('sanitises the stored filename against traversal', function () {
    Storage::fake('local');

    $url = (new ExportToSignedUrl('x', '../../etc/passwd'))->handle();

    // Whatever the agent asked for, the stored file lives inside the export
    // directory with a safe name.
    $files = Storage::disk('local')->allFiles('mcp-kit/exports');

    expect($files)->toHaveCount(1)
        ->and($files[0])->not->toContain('..')
        ->and($url)->toContain('/mcp-kit/exports/');
});
