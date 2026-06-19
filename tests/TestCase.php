<?php

namespace CleaniqueCoders\LaravelMcpKit\Tests;

use CleaniqueCoders\LaravelMcpKit\LaravelMcpKitServiceProvider;
use CleaniqueCoders\LaravelMcpKit\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
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

        // Generic-toolbox gates: each configured ability is granted when its
        // config KEY (or '*') appears in the user's grants array. Lets a test
        // user hold, say, ['view-logs'] or ['*'] for everything.
        foreach ((array) config('mcp-kit.abilities', []) as $key => $ability) {
            if (in_array($key, ['view-tasks', 'manage-tasks'], true) || ! is_string($ability)) {
                continue;
            }

            Gate::define($ability, fn ($user): bool => in_array('*', $user->grants ?? [], true)
                || in_array($key, $user->grants ?? [], true));
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            // laravel/mcp's own provider registers the resolving(Request)
            // callback that copies tool arguments into the injected Request.
            // Testbench does not auto-load it, so we must list it explicitly.
            McpServiceProvider::class,
            // Registers the `sanctum` auth driver used by the HTTP endpoint.
            SanctumServiceProvider::class,
            LaravelMcpKitServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // The runtime toggle is cache-backed; use the array store in tests so
        // it never reaches for a (non-existent) database cache table.
        config()->set('cache.default', 'array');

        // Resolve the demo User fixture as the auth provider model so the
        // mcp-kit:token command finds users via config('auth.providers.users.model').
        config()->set('auth.providers.users.model', User::class);

        // Define the `sanctum` guard the HTTP endpoint authenticates with.
        config()->set('auth.guards.sanctum', ['driver' => 'sanctum', 'provider' => 'users']);

        // Queue wiring for the failed-jobs tools: a database queue connection
        // to push retried jobs onto, and the uuid failed-jobs provider.
        config()->set('queue.connections.database', [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'connection' => 'testing',
        ]);
        config()->set('queue.failed', [
            'driver' => 'database-uuids',
            'database' => 'testing',
            'table' => 'failed_jobs',
        ]);

        // Load the package migration (kept as a .php.stub for publishing).
        foreach (File::allFiles(__DIR__.'/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }

        // A minimal users table for the fixture User, plus Sanctum's token
        // table so HasApiTokens / the token command work in the suite.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->json('grants')->nullable();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Core Laravel queue tables the jobs tools read/write.
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }
}
