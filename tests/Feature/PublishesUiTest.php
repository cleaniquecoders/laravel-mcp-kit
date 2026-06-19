<?php

use CleaniqueCoders\LaravelMcpKit\LaravelMcpKitServiceProvider;
use Illuminate\Support\ServiceProvider;

it('registers the mcp-kit-ui publish group with the token UI and toggle card stubs', function () {
    $paths = ServiceProvider::pathsToPublish(LaravelMcpKitServiceProvider::class, 'mcp-kit-ui');

    expect($paths)->not->toBeEmpty()
        ->and(collect($paths)->values()->all())->toContain(
            app_path('Livewire/McpTokens.php'),
            resource_path('views/livewire/mcp-tokens.blade.php'),
            app_path('Livewire/McpToggleCard.php'),
            resource_path('views/livewire/mcp-toggle-card.blade.php'),
            app_path('Livewire/McpSettings.php'),
            resource_path('views/livewire/mcp-settings.blade.php'),
        );
});
