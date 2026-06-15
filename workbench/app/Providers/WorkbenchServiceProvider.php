<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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
    }

    public function boot(): void
    {
        // Demo gates. A real app backs these with its own permission system
        // (a Policy, spatie/laravel-permission, etc.). Here `is_manager`
        // decides who may write — everyone may read.
        Gate::define('mcp-kit.view-tasks', fn ($user) => true);
        Gate::define('mcp-kit.manage-tasks', fn ($user) => (bool) ($user->is_manager ?? false));

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

        if (class_exists(Passport::class)) {
            // Passport 13 ships no consent view — use the package's stub.
            Passport::authorizationView('mcp-kit::authorize');

            // Passport 13 does not auto-load its migrations; load them here
            // for the workbench so `migrate` creates the oauth_* tables.
            $this->loadMigrationsFrom(
                dirname(__DIR__, 3).'/vendor/laravel/passport/database/migrations'
            );
        }
    }
}
