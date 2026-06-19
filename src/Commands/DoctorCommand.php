<?php

namespace CleaniqueCoders\LaravelMcpKit\Commands;

use CleaniqueCoders\LaravelMcpKit\Support\McpConfigSnapshot;
use Illuminate\Console\Command;

/**
 * Diagnose the MCP Kit wiring: transports, auth, OAuth keys, tables, and which
 * Tier-2 tools auto-registered. The fast answer to "why is my tool returning
 * unauthorized / 401 / 404?" before you go spelunking.
 *
 * The checks themselves live in {@see McpConfigSnapshot} so the browser settings
 * UI reports exactly the same results.
 *
 *   php artisan mcp-kit:doctor
 */
class DoctorCommand extends Command
{
    protected $signature = 'mcp-kit:doctor';

    protected $description = 'Verify MCP Kit token / transport / OAuth wiring';

    public function handle(McpConfigSnapshot $snapshot): int
    {
        $this->components->info('MCP Kit doctor');

        $checks = $snapshot->doctor();

        $this->newLine();

        foreach ($checks as $check) {
            $this->components->twoColumnDetail(
                $this->badge($check['level']).' '.$check['label'],
                $check['detail'],
            );
        }

        $failed = collect($checks)->where('level', 'fail')->count();

        $this->newLine();

        if ($failed > 0) {
            $this->components->error("{$failed} check(s) failed — see above.");

            return self::FAILURE;
        }

        $this->components->info('All critical checks passed.');

        return self::SUCCESS;
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
