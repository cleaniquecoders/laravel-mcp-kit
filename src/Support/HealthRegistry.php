<?php

namespace CleaniqueCoders\LaravelMcpKit\Support;

use Throwable;

/**
 * A registry of named, app-defined connectivity checks the generic
 * `system_health` tool reports alongside the core (db/cache/queue/storage)
 * checks.
 *
 * This is the generalisation of the per-app "is Keycloak/LDAP/Oracle/Kong
 * reachable?" tools we kept rewriting: instead of a bespoke tool per
 * dependency, a host registers a closure and the one health tool surfaces it.
 *
 *   // In a service provider:
 *   Mcp::healthCheck('keycloak', fn () => Http::get(config('keycloak.url'))->ok());
 *
 * A check returns either a bool (ok / not ok) or an array with a `healthy`
 * key plus any extra detail. Thrown exceptions are caught and reported as
 * unhealthy so one broken check can never take the whole tool down.
 *
 * Registered as a singleton so registrations survive for the request.
 */
class HealthRegistry
{
    /**
     * @var array<string, callable():(bool|array<string, mixed>)>
     */
    protected array $checks = [];

    /**
     * Register (or replace) a named check.
     *
     * @param  callable():(bool|array<string, mixed>)  $check
     */
    public function register(string $name, callable $check): void
    {
        $this->checks[$name] = $check;
    }

    public function has(string $name): bool
    {
        return isset($this->checks[$name]);
    }

    public function forget(string $name): void
    {
        unset($this->checks[$name]);
    }

    /**
     * @return array<string, callable():(bool|array<string, mixed>)>
     */
    public function all(): array
    {
        return $this->checks;
    }

    /**
     * Run every registered check, normalising each result to a status array.
     *
     * @return array<string, array<string, mixed>>
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->checks as $name => $check) {
            $results[$name] = $this->evaluate($check);
        }

        return $results;
    }

    /**
     * @param  callable():(bool|array<string, mixed>)  $check
     * @return array<string, mixed>
     */
    protected function evaluate(callable $check): array
    {
        try {
            $result = $check();
        } catch (Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }

        if (is_array($result)) {
            return ['healthy' => (bool) ($result['healthy'] ?? false)] + $result;
        }

        return ['healthy' => (bool) $result];
    }
}
