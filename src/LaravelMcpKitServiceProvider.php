<?php

namespace CleaniqueCoders\LaravelMcpKit;

use CleaniqueCoders\LaravelMcpKit\Commands\IssueTokenCommand;
use CleaniqueCoders\LaravelMcpKit\Commands\SeedDemoTasksCommand;
use Laravel\Passport\Passport;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMcpKitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This package is configured with spatie/laravel-package-tools.
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('mcp-kit')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_mcp_kit_tasks_table')
            ->hasCommand(SeedDemoTasksCommand::class)
            ->hasCommand(IssueTokenCommand::class);
    }

    public function packageBooted(): void
    {
        $this->configureOAuth();

        // Register the MCP servers (STDIO + HTTP) declared in routes/ai.php.
        // Guarded on the route cache so a cached HTTP route table is not
        // mutated at boot. In your own app you would instead load this from
        // bootstrap/app.php withRouting(then: ...) like the reference does.
        if (! $this->app->routesAreCached()) {
            require __DIR__.'/../routes/ai.php';
        }
    }

    /**
     * Wire Passport for the OAuth transport, but only when OAuth is enabled
     * and Passport is actually installed. Both are non-destructive: the
     * `api` guard is only added if the host has not defined one, so a real
     * app's own config/auth.php always wins.
     */
    protected function configureOAuth(): void
    {
        if (! config('mcp-kit.web.oauth.enabled', false)) {
            return;
        }

        if (! class_exists(Passport::class)) {
            return;
        }

        if (config('auth.guards.api') === null) {
            config(['auth.guards.api' => [
                'driver' => 'passport',
                'provider' => 'users',
            ]]);
        }

        Passport::tokensExpireIn(
            now()->addHours((int) config('mcp-kit.web.oauth.access_token_hours', 12))
        );

        Passport::refreshTokensExpireIn(
            now()->addDays((int) config('mcp-kit.web.oauth.refresh_token_days', 30))
        );
    }
}
