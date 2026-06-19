<?php

namespace CleaniqueCoders\LaravelMcpKit\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * A runtime on/off switch for the MCP server, independent of the
 * `MCP_KIT_ENABLED` env flag.
 *
 * Every host eventually needs to turn MCP off without a redeploy — to shed
 * load, during an incident, or to dark-launch. The env flag stays the
 * deploy-time MASTER kill-switch; this is the operator-time toggle that
 * layers under it:
 *
 *     enabled() === config('mcp-kit.enabled') AND cached-flag
 *
 * routes/ai.php reads {@see enabled()} when it registers the server, so the
 * flag is consulted as routes are built. That means flipping it must invalidate
 * a cached route table — {@see set()} clears the route cache on change so the
 * next request re-evaluates routes/ai.php and (de)registers the endpoint.
 *
 * Backed by the cache (see `mcp-kit.toggle`). Use a SHARED store
 * (redis/database/file) so web, queue and CLI processes all agree.
 */
class McpToggle
{
    public static function key(): string
    {
        return (string) config('mcp-kit.toggle.key', 'mcp-kit.runtime-enabled');
    }

    /**
     * Whether MCP is currently on. False whenever the env master switch is off,
     * regardless of the cached flag.
     */
    public static function enabled(): bool
    {
        if (! config('mcp-kit.enabled', true)) {
            return false;
        }

        $default = (bool) config('mcp-kit.toggle.default', true);

        try {
            return (bool) static::store()->get(static::key(), $default);
        } catch (\Throwable) {
            // routes/ai.php reads this at boot; a cache-store hiccup (e.g. an
            // unmigrated database store) must never take the whole app down.
            return $default;
        }
    }

    public static function enable(): void
    {
        static::set(true);
    }

    public static function disable(): void
    {
        static::set(false);
    }

    /**
     * Persist the flag and keep the route cache honest.
     */
    public static function set(bool $value): void
    {
        static::store()->forever(static::key(), $value);

        // routes/ai.php registers the server conditionally on enabled(); a
        // cached route table would otherwise pin the old decision until the
        // next deploy. Refresh it so the change takes effect immediately.
        if (App::routesAreCached()) {
            Artisan::call('route:clear');
        }
    }

    /**
     * Drop the override so {@see enabled()} falls back to the configured
     * default.
     */
    public static function reset(): void
    {
        static::store()->forget(static::key());

        if (App::routesAreCached()) {
            Artisan::call('route:clear');
        }
    }

    protected static function store(): Repository
    {
        return Cache::store(config('mcp-kit.toggle.store'));
    }
}
