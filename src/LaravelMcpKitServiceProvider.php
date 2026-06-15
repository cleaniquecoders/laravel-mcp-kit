<?php

namespace CleaniqueCoders\LaravelMcpKit;

use CleaniqueCoders\LaravelMcpKit\Commands\IssueTokenCommand;
use CleaniqueCoders\LaravelMcpKit\Commands\SeedDemoTasksCommand;
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
            ->hasMigration('create_mcp_kit_tasks_table')
            ->hasCommand(SeedDemoTasksCommand::class)
            ->hasCommand(IssueTokenCommand::class);
    }

    public function packageBooted(): void
    {
        // Register the MCP servers (STDIO + HTTP) declared in routes/ai.php.
        // Guarded on the route cache so a cached HTTP route table is not
        // mutated at boot. In your own app you would instead load this from
        // bootstrap/app.php withRouting(then: ...) like the reference does.
        if (! $this->app->routesAreCached()) {
            require __DIR__.'/../routes/ai.php';
        }
    }
}
