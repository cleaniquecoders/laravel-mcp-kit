<?php

use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;
use CleaniqueCoders\LaravelMcpKit\Tools\ExportLogsTool;
use CleaniqueCoders\LaravelMcpKit\Tools\SearchLogsTool;
use CleaniqueCoders\LaravelMcpKit\Tools\TailLogsTool;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->logDir = sys_get_temp_dir().'/mcp-kit-logs-'.uniqid();
    File::ensureDirectoryExists($this->logDir);

    File::put($this->logDir.'/laravel.log', implode("\n", [
        '[2026-06-19 10:00:00] testing.INFO: application booted',
        '[2026-06-19 10:01:00] testing.ERROR: payment gateway timeout',
        '#0 /app/Services/Pay.php(42): timeout()',
        '[2026-06-19 10:02:00] testing.WARNING: cache miss storm',
        '',
    ]));

    config()->set('mcp-kit.ops.logs.path', $this->logDir);
});

afterEach(function () {
    File::deleteDirectory($this->logDir);
});

it('tails recent log entries', function () {
    TaskServer::actingAs(granted(['view-logs']))
        ->tool(TailLogsTool::class, ['lines' => 10])
        ->assertOk()
        ->assertSee('payment gateway timeout')
        ->assertSee('laravel.log');
});

it('filters the tail by level, keeping the entry and its stack trace', function () {
    TaskServer::actingAs(granted(['view-logs']))
        ->tool(TailLogsTool::class, ['level' => 'error'])
        ->assertOk()
        ->assertSee('payment gateway timeout')
        ->assertSee('Services/Pay.php')
        ->assertDontSee('cache miss storm');
});

it('blocks a user without the view-logs ability from tailing', function () {
    TaskServer::actingAs(nobody())
        ->tool(TailLogsTool::class)
        ->assertHasErrors();
});

it('searches log entries by free text', function () {
    TaskServer::actingAs(granted(['view-logs']))
        ->tool(SearchLogsTool::class, ['query' => 'timeout'])
        ->assertOk()
        ->assertSee('payment gateway timeout')
        ->assertSee('"total_matches":1');
});

it('requires a query or level to search', function () {
    TaskServer::actingAs(granted(['view-logs']))
        ->tool(SearchLogsTool::class, [])
        ->assertHasErrors();
});

it('exports a filtered slice and returns a signed download url', function () {
    Storage::fake('local');

    TaskServer::actingAs(granted(['export-logs']))
        ->tool(ExportLogsTool::class, ['level' => 'error'])
        ->assertOk()
        ->assertSee('download_url')
        ->assertSee('/mcp-kit/exports/');

    expect(Storage::disk('local')->allFiles('mcp-kit/exports'))->toHaveCount(1);
});

it('errors when an export matches nothing', function () {
    Storage::fake('local');

    TaskServer::actingAs(granted(['export-logs']))
        ->tool(ExportLogsTool::class, ['query' => 'no-such-text-anywhere'])
        ->assertHasErrors();
});

it('blocks a view-logs user from exporting (export needs its own ability)', function () {
    TaskServer::actingAs(granted(['view-logs']))
        ->tool(ExportLogsTool::class, ['level' => 'error'])
        ->assertHasErrors();
});
