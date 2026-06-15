<?php

use CleaniqueCoders\LaravelMcpKit\LaravelMcpKitServiceProvider;
use Illuminate\Support\ServiceProvider;

it('registers the mcp-kit-ui publish group with the token UI stubs', function () {
    $paths = ServiceProvider::pathsToPublish(LaravelMcpKitServiceProvider::class, 'mcp-kit-ui');

    expect($paths)->not->toBeEmpty()
        ->and(collect($paths)->values()->all())->toContain(
            app_path('Livewire/McpTokens.php'),
            resource_path('views/livewire/mcp-tokens.blade.php'),
        );
});
