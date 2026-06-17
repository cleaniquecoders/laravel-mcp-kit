<?php

namespace CleaniqueCoders\LaravelMcpKit;

use CleaniqueCoders\LaravelMcpKit\Commands\InstallCommand;
use CleaniqueCoders\LaravelMcpKit\Commands\IssueTokenCommand;
use CleaniqueCoders\LaravelMcpKit\Commands\SeedDemoTasksCommand;
use Laravel\Passport\Passport;
use ReflectionClass;
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
            ->hasCommand(InstallCommand::class)
            ->hasCommand(SeedDemoTasksCommand::class)
            ->hasCommand(IssueTokenCommand::class);
    }

    public function packageBooted(): void
    {
        $this->configureOAuth();

        // Publishable Livewire + Flux token-management UI (optional).
        // Requires livewire/livewire + livewire/flux in the host app:
        //   php artisan vendor:publish --tag="mcp-kit-ui"
        $this->publishes([
            __DIR__.'/../stubs/Livewire/McpTokens.php.stub' => app_path('Livewire/McpTokens.php'),
            __DIR__.'/../stubs/views/mcp-tokens.blade.php.stub' => resource_path('views/livewire/mcp-tokens.blade.php'),
        ], 'mcp-kit-ui');

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
     * and Passport is actually installed. Everything here is non-destructive:
     * the `api` guard is only added if the host has not defined one, the
     * consent view and migration loading are opt-out via config, so a real
     * app's own config always wins.
     *
     * The goal is one-flag OAuth: set `MCP_KIT_WEB_OAUTH_ENABLED=true`, run
     * `migrate`, generate keys — no service-provider edits, no extra publish
     * steps. `mcp-kit:install --oauth` does the last two for you.
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

        // Consent screen for the auth-code flow. Passport 13 ships none, so
        // wire our publishable stub unless the host opted out (false) or
        // pointed the config at their own branded view.
        $view = config('mcp-kit.web.oauth.authorization_view', 'mcp-kit::authorize');

        if ($view !== false && $view !== null) {
            Passport::authorizationView($view);
        }

        // Passport 13 no longer auto-loads its oauth_* migrations. Register
        // them so a plain `migrate` creates the tables — saving the host a
        // `vendor:publish --tag=passport-migrations` step.
        if (config('mcp-kit.web.oauth.load_migrations', true)) {
            $passportMigrations = dirname((new ReflectionClass(Passport::class))->getFileName(), 2)
                .'/database/migrations';

            if (is_dir($passportMigrations)) {
                $this->loadMigrationsFrom($passportMigrations);
            }
        }
    }
}
