<?php

namespace CleaniqueCoders\LaravelMcpKit\Tests;

use CleaniqueCoders\LaravelMcpKit\LaravelMcpKitServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'CleaniqueCoders\\LaravelMcpKit\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Demo gates. In a real app these are backed by roles / a
        // permission package; here a simple `grants` array on the user
        // stands in. The tools only ever check the ability name.
        Gate::define('mcp-kit.view-tasks', fn ($user) => in_array('view', $user->grants ?? [], true));
        Gate::define('mcp-kit.manage-tasks', fn ($user) => in_array('manage', $user->grants ?? [], true));
    }

    protected function getPackageProviders($app)
    {
        return [
            // laravel/mcp's own provider registers the resolving(Request)
            // callback that copies tool arguments into the injected Request.
            // Testbench does not auto-load it, so we must list it explicitly.
            McpServiceProvider::class,
            LaravelMcpKitServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Load the package migration (kept as a .php.stub for publishing).
        foreach (File::allFiles(__DIR__.'/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }
}
