<?php

namespace CleaniqueCoders\LaravelMcpKit\Support;

use CleaniqueCoders\LaravelMcpKit\Servers\ToolRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;
use Throwable;

/**
 * A read-only view of how the kit is wired right now: the effective
 * configuration, the `mcp-kit:doctor` checks, the live tool registry, and the
 * ability map.
 *
 * One source of truth for the browser settings UI and the `mcp-kit:doctor`
 * command — neither reimplements the checks. Purely read-only: it never mutates
 * anything (the only writable control, the runtime toggle, lives in
 * {@see McpToggle}).
 */
class McpConfigSnapshot
{
    /**
     * The effective configuration, as display-ready label => value pairs.
     *
     * @return array<string, string>
     */
    public function effectiveConfig(): array
    {
        $oauth = (bool) config('mcp-kit.web.oauth.enabled', false);

        return [
            'STDIO transport' => config('mcp-kit.local.enabled', true)
                ? 'on — handle: '.config('mcp-kit.local.handle', 'mcp-kit')
                : 'off',
            'STDIO local user' => (string) (config('mcp-kit.local.user') ?: '(none — stdio stays gated)'),
            'HTTP transport' => config('mcp-kit.web.enabled', true)
                ? 'on — path: '.config('mcp-kit.web.path', 'mcp/tasks')
                : 'off',
            'HTTP throttle' => (string) config('mcp-kit.web.throttle', '60,1'),
            'Computed middleware' => implode(', ', config('mcp-kit.web.middleware') ?? [
                $oauth ? 'auth:sanctum,api' : 'auth:sanctum',
                'throttle:'.config('mcp-kit.web.throttle', '60,1'),
            ]),
            'OAuth 2.1' => $oauth ? 'on' : 'off (token-only)',
            'Logs path' => (string) config('mcp-kit.ops.logs.path'),
            'Export disk / TTL' => config('mcp-kit.ops.export.disk', 'local').' / '.config('mcp-kit.ops.export.ttl', 15).' min',
            'Token prefix' => (string) config('mcp-kit.tokens.prefix', 'mcp-kit'),
        ];
    }

    /**
     * The ability each tool checks (the host defines who holds them).
     *
     * @return array<string, string>
     */
    public function abilities(): array
    {
        /** @var array<string, string> $abilities */
        $abilities = array_filter((array) config('mcp-kit.abilities', []), 'is_string');

        return $abilities;
    }

    /**
     * The live tool registry, with the package-gated (Tier-2) tools flagged.
     *
     * @return array<int, array{name: string, gated: bool}>
     */
    public function tools(): array
    {
        $gated = ToolRegistry::packageGatedTools();

        return collect(ToolRegistry::tools())
            ->map(fn (string $tool): array => [
                'name' => class_basename($tool),
                'gated' => in_array($tool, $gated, true),
            ])
            ->values()
            ->all();
    }

    /**
     * The `mcp-kit:doctor` checks as structured rows (level: ok|warn|fail).
     *
     * @return array<int, array{level: string, label: string, detail: string}>
     */
    public function doctor(): array
    {
        return array_merge(
            $this->enabledChecks(),
            $this->transportChecks(),
            $this->authChecks(),
            $this->oauthChecks(),
            $this->tier2Checks(),
        );
    }

    /**
     * @return array<int, array{level: string, label: string, detail: string}>
     */
    protected function enabledChecks(): array
    {
        $master = (bool) config('mcp-kit.enabled', true);

        return [
            $this->row($master ? 'ok' : 'warn', 'Master switch (MCP_KIT_ENABLED)', $master ? 'on' : 'off'),
            $this->row(McpToggle::enabled() ? 'ok' : 'warn', 'Runtime toggle', McpToggle::enabled() ? 'enabled' : 'disabled'),
        ];
    }

    /**
     * @return array<int, array{level: string, label: string, detail: string}>
     */
    protected function transportChecks(): array
    {
        return [
            $this->row(
                config('mcp-kit.local.enabled', true) ? 'ok' : 'warn',
                'STDIO transport',
                config('mcp-kit.local.enabled', true) ? 'handle: '.config('mcp-kit.local.handle', 'mcp-kit') : 'disabled',
            ),
            $this->row(
                config('mcp-kit.web.enabled', true) ? 'ok' : 'warn',
                'HTTP transport',
                config('mcp-kit.web.enabled', true) ? 'path: '.config('mcp-kit.web.path', 'mcp/tasks') : 'disabled',
            ),
            $this->row(
                Route::has('mcp-kit.tasks') ? 'ok' : 'warn',
                'HTTP route registered',
                Route::has('mcp-kit.tasks') ? 'mcp-kit.tasks' : 'not found (cached routes? toggle off?)',
            ),
            $this->row(
                Route::has('mcp-kit.download') ? 'ok' : 'warn',
                'Export download route',
                Route::has('mcp-kit.download') ? 'mcp-kit.download' : 'not found',
            ),
        ];
    }

    /**
     * @return array<int, array{level: string, label: string, detail: string}>
     */
    protected function authChecks(): array
    {
        $sanctum = class_exists(Sanctum::class);

        $rows = [
            $this->row($sanctum ? 'ok' : 'fail', 'laravel/sanctum installed', $sanctum ? 'yes' : 'required for the HTTP endpoint'),
        ];

        try {
            $hasTable = Schema::hasTable('personal_access_tokens');
        } catch (Throwable) {
            $hasTable = false;
        }

        $rows[] = $this->row($hasTable ? 'ok' : 'warn', 'personal_access_tokens table', $hasTable ? 'present' : 'run migrate');

        /** @var class-string $model */
        $model = config('auth.providers.users.model', 'App\\Models\\User');
        $usesTokens = class_exists($model) && method_exists($model, 'createToken');
        $rows[] = $this->row($usesTokens ? 'ok' : 'warn', 'User model uses HasApiTokens', $usesTokens ? class_basename($model) : 'add Laravel\\Sanctum\\HasApiTokens');

        $localUser = config('mcp-kit.local.user');

        if ($localUser !== null) {
            $found = class_exists($model) && $model::query()->where('email', $localUser)->exists();
            $rows[] = $this->row($found ? 'ok' : 'warn', 'STDIO local user resolvable', $found ? (string) $localUser : "no user [{$localUser}]");
        }

        return $rows;
    }

    /**
     * @return array<int, array{level: string, label: string, detail: string}>
     */
    protected function oauthChecks(): array
    {
        if (! config('mcp-kit.web.oauth.enabled', false)) {
            return [$this->row('ok', 'OAuth transport', 'off (token-only)')];
        }

        $passport = class_exists(Passport::class);
        $rows = [$this->row($passport ? 'ok' : 'fail', 'laravel/passport installed', $passport ? 'yes' : 'required when OAuth is on')];

        if (! $passport) {
            return $rows;
        }

        $keysPresent = file_exists(Passport::keyPath('oauth-private.key'))
            && file_exists(Passport::keyPath('oauth-public.key'));

        $rows[] = $this->row(
            $keysPresent ? 'ok' : 'fail',
            'Passport encryption keys',
            $keysPresent ? 'present' : 'run php artisan passport:keys',
        );

        return $rows;
    }

    /**
     * @return array<int, array{level: string, label: string, detail: string}>
     */
    protected function tier2Checks(): array
    {
        $registered = ToolRegistry::tools();
        $active = array_values(array_intersect(ToolRegistry::packageGatedTools(), $registered));

        return [
            $this->row('ok', 'Tools registered', count($registered).' total'),
            $this->row(
                'ok',
                'Package-gated tools active',
                $active === []
                    ? 'none (install sanctum/auditing/permission/activitylog)'
                    : count($active).': '.implode(', ', array_map('class_basename', $active)),
            ),
        ];
    }

    /**
     * @return array{level: string, label: string, detail: string}
     */
    protected function row(string $level, string $label, string $detail): array
    {
        return ['level' => $level, 'label' => $label, 'detail' => $detail];
    }
}
