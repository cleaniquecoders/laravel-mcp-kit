<?php

namespace CleaniqueCoders\LaravelMcpKit\Tests;

use Illuminate\Support\Facades\File;
use Laravel\Passport\PassportServiceProvider;

/**
 * Harness for the OAuth transport. It boots with OAuth enabled (so the
 * provider auto-registers the `api` Passport guard and routes/ai.php
 * registers Mcp::oauthRoutes()), pulls in Passport's provider, and loads
 * the oauth_* tables. Used by the OAuth flow tests.
 */
class OAuthTestCase extends TestCase
{
    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [
            PassportServiceProvider::class,
        ]);
    }

    public function getEnvironmentSetUp($app)
    {
        // Must be set before boot: the provider reads it in packageBooted
        // (to wire the api guard) and routes/ai.php reads it to register
        // the OAuth discovery + registration routes.
        config()->set('mcp-kit.web.oauth.enabled', true);

        // Passport's token issuance touches the cache; keep it in-memory so
        // the suite needs no `cache` table.
        config()->set('cache.default', 'array');

        // Pin the redirect allow-list the DCR endpoint validates against.
        config()->set('mcp.redirect_domains', [
            'https://claude.ai',
            'https://claude.com',
            'http://localhost',
            'http://127.0.0.1',
        ]);

        parent::getEnvironmentSetUp($app);

        // Passport's oauth_* tables (no keys needed for discovery/DCR).
        foreach (File::allFiles(dirname(__DIR__).'/vendor/laravel/passport/database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }
}
