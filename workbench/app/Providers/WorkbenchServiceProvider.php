<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Workbench\App\Livewire\McpSettings;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Testbench defaults to an in-memory `testing` connection, which the
        // `serve` process and the CLI would not share. Pin a persistent file
        // sqlite so seeded data, tokens, and OAuth clients survive requests.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => dirname(__DIR__, 3).'/workbench/database/database.sqlite',
        ]);

        // Livewire is a dev dependency and testbench's package discovery does
        // not pick it up for `serve`, so register it here (the harness for the
        // MCP settings UI, issue #16). Idempotent — Laravel dedupes providers.
        $this->app->register(LivewireServiceProvider::class);
    }

    public function boot(): void
    {
        // Demo gates. A real app backs these with its own permission system
        // (a Policy, spatie/laravel-permission, etc.). Here `is_manager`
        // decides who may write — everyone may read.
        Gate::define('mcp-kit.view-tasks', fn ($user) => true);
        Gate::define('mcp-kit.manage-tasks', fn ($user) => (bool) ($user->is_manager ?? false));

        // Who may flip the runtime MCP toggle / reach the settings UI.
        Gate::define('mcp-kit.manage-mcp', fn ($user) => (bool) ($user->is_manager ?? false));

        // The MCP settings UI harness (issue #16). Register the Livewire
        // component and make the workbench views resolvable.
        view()->addLocation(dirname(__DIR__, 2).'/resources/views');
        Livewire::component('mcp-settings', McpSettings::class);

        // Sanctum's personal_access_tokens table (not auto-loaded here).
        $this->loadMigrationsFrom(
            dirname(__DIR__, 3).'/vendor/laravel/sanctum/database/migrations'
        );

        // The OAuth redirect allow-list a real app sets in config/mcp.php.
        config(['mcp.redirect_domains' => [
            'https://claude.ai',
            'https://claude.com',
            'http://localhost',
            'http://127.0.0.1',
        ]]);

        // Note: the consent view (mcp-kit::authorize) and Passport's oauth_*
        // migrations are wired by the package itself when OAuth is enabled —
        // see LaravelMcpKitServiceProvider::configureOAuth(). The workbench
        // gets them for free, exactly like a host app would.
    }
}
