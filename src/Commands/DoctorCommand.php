<?php

namespace CleaniqueCoders\LaravelMcpKit\Commands;

use CleaniqueCoders\LaravelMcpKit\Servers\ToolRegistry;
use CleaniqueCoders\LaravelMcpKit\Support\McpToggle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;
use Throwable;

/**
 * Diagnose the MCP Kit wiring: transports, auth, OAuth keys, tables, and which
 * Tier-2 tools auto-registered. The fast answer to "why is my tool returning
 * unauthorized / 401 / 404?" before you go spelunking.
 *
 *   php artisan mcp-kit:doctor
 */
class DoctorCommand extends Command
{
    protected $signature = 'mcp-kit:doctor';

    protected $description = 'Verify MCP Kit token / transport / OAuth wiring';

    /**
     * @var array<int, array{level: string, label: string, detail: string}>
     */
    protected array $checks = [];

    public function handle(): int
    {
        $this->components->info('MCP Kit doctor');

        $this->checkEnabled();
        $this->checkTransports();
        $this->checkAuth();
        $this->checkOAuth();
        $this->checkTier2();

        $this->newLine();

        foreach ($this->checks as $check) {
            $this->components->twoColumnDetail(
                $this->badge($check['level']).' '.$check['label'],
                $check['detail'],
            );
        }

        $failed = collect($this->checks)->where('level', 'fail')->count();

        $this->newLine();

        if ($failed > 0) {
            $this->components->error("{$failed} check(s) failed — see above.");

            return self::FAILURE;
        }

        $this->components->info('All critical checks passed.');

        return self::SUCCESS;
    }

    protected function checkEnabled(): void
    {
        $master = (bool) config('mcp-kit.enabled', true);
        $this->add($master ? 'ok' : 'warn', 'Master switch (MCP_KIT_ENABLED)', $master ? 'on' : 'off');

        $this->add(
            McpToggle::enabled() ? 'ok' : 'warn',
            'Runtime toggle',
            McpToggle::enabled() ? 'enabled' : 'disabled',
        );
    }

    protected function checkTransports(): void
    {
        $this->add(
            config('mcp-kit.local.enabled', true) ? 'ok' : 'warn',
            'STDIO transport',
            config('mcp-kit.local.enabled', true) ? 'handle: '.config('mcp-kit.local.handle', 'mcp-kit') : 'disabled',
        );

        $this->add(
            config('mcp-kit.web.enabled', true) ? 'ok' : 'warn',
            'HTTP transport',
            config('mcp-kit.web.enabled', true) ? 'path: '.config('mcp-kit.web.path', 'mcp/tasks') : 'disabled',
        );

        $this->add(
            Route::has('mcp-kit.tasks') ? 'ok' : 'warn',
            'HTTP route registered',
            Route::has('mcp-kit.tasks') ? 'mcp-kit.tasks' : 'not found (cached routes? toggle off?)',
        );

        $this->add(
            Route::has('mcp-kit.download') ? 'ok' : 'warn',
            'Export download route',
            Route::has('mcp-kit.download') ? 'mcp-kit.download' : 'not found',
        );
    }

    protected function checkAuth(): void
    {
        $sanctum = class_exists(Sanctum::class);
        $this->add($sanctum ? 'ok' : 'fail', 'laravel/sanctum installed', $sanctum ? 'yes' : 'required for the HTTP endpoint');

        try {
            $hasTable = Schema::hasTable('personal_access_tokens');
        } catch (Throwable $e) {
            $hasTable = false;
        }

        $this->add($hasTable ? 'ok' : 'warn', 'personal_access_tokens table', $hasTable ? 'present' : 'run migrate');

        /** @var class-string $model */
        $model = config('auth.providers.users.model', 'App\\Models\\User');
        $usesTokens = class_exists($model) && method_exists($model, 'createToken');
        $this->add($usesTokens ? 'ok' : 'warn', 'User model uses HasApiTokens', $usesTokens ? class_basename($model) : 'add Laravel\\Sanctum\\HasApiTokens');

        $localUser = config('mcp-kit.local.user');

        if ($localUser !== null) {
            $found = class_exists($model)
                && $model::query()->where('email', $localUser)->exists();
            $this->add($found ? 'ok' : 'warn', 'STDIO local user resolvable', $found ? (string) $localUser : "no user [{$localUser}]");
        }
    }

    protected function checkOAuth(): void
    {
        if (! config('mcp-kit.web.oauth.enabled', false)) {
            $this->add('ok', 'OAuth transport', 'off (token-only)');

            return;
        }

        $passport = class_exists(Passport::class);
        $this->add($passport ? 'ok' : 'fail', 'laravel/passport installed', $passport ? 'yes' : 'required when OAuth is on');

        if (! $passport) {
            return;
        }

        $keysPresent = file_exists(Passport::keyPath('oauth-private.key'))
            && file_exists(Passport::keyPath('oauth-public.key'));

        $this->add(
            $keysPresent ? 'ok' : 'fail',
            'Passport encryption keys',
            $keysPresent ? 'present' : 'run php artisan passport:keys',
        );
    }

    protected function checkTier2(): void
    {
        $registered = ToolRegistry::tools();
        $active = array_values(array_intersect(ToolRegistry::packageGatedTools(), $registered));

        $this->add('ok', 'Tools registered', (string) count($registered).' total');

        $this->add(
            'ok',
            'Package-gated tools active',
            $active === []
                ? 'none (install sanctum/auditing/permission/activitylog)'
                : count($active).': '.implode(', ', array_map('class_basename', $active)),
        );
    }

    protected function add(string $level, string $label, string $detail): void
    {
        $this->checks[] = ['level' => $level, 'label' => $label, 'detail' => $detail];
    }

    protected function badge(string $level): string
    {
        return match ($level) {
            'ok' => '<fg=green>✓</>',
            'warn' => '<fg=yellow>!</>',
            default => '<fg=red>✗</>',
        };
    }
}
