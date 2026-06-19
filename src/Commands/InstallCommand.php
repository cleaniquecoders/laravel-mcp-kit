<?php

namespace CleaniqueCoders\LaravelMcpKit\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\Passport;

/**
 * One-shot setup for the MCP Kit in a host app.
 *
 * Collapses the "publish config, publish migrations, set up Passport, wire a
 * consent view" dance into a single command so the kit can be dropped into
 * many projects the same way every time:
 *
 *   php artisan mcp-kit:install            # token-only (Sanctum) transport
 *   php artisan mcp-kit:install --oauth    # also stand up the OAuth 2.1 flow
 *   php artisan mcp-kit:install --oauth --ui
 *
 * Everything it does is idempotent; re-run with --force to overwrite files.
 */
class InstallCommand extends Command
{
    protected $signature = 'mcp-kit:install
        {--oauth : Also stand up the OAuth 2.1 (Passport) transport}
        {--ui : Also publish the Livewire + Flux token-management UI}
        {--force : Overwrite any files that were already published}';

    protected $description = 'Install the MCP Kit: publish config/migrations and (optionally) wire OAuth in one step';

    public function handle(): int
    {
        $this->components->info('Installing the Laravel MCP Kit');

        $this->publish('mcp-kit-config', 'config');
        $this->publish('mcp-kit-migrations', 'migration');

        if ($this->option('oauth') && ! $this->installOAuth()) {
            return self::FAILURE;
        }

        if ($this->option('ui')) {
            $this->installUi();
        }

        $this->nextSteps();

        return self::SUCCESS;
    }

    /**
     * Publish a tagged group, reporting it as one task line.
     */
    protected function publish(string $tag, string $label): void
    {
        $this->components->task("Publishing {$label} ({$tag})", function () use ($tag) {
            $this->callSilently('vendor:publish', array_filter([
                '--tag' => $tag,
                '--force' => $this->option('force') ?: null,
            ]));

            return true;
        });
    }

    /**
     * Stand up the OAuth 2.1 transport: Passport keys, oauth_* tables, and a
     * consent view. The runtime wiring (api guard, the consent view binding,
     * loading Passport's migrations) is handled by the service provider once
     * MCP_KIT_WEB_OAUTH_ENABLED=true — this command just runs the one-time
     * artisan steps a host would otherwise look up and run by hand.
     */
    protected function installOAuth(): bool
    {
        if (! class_exists(Passport::class)) {
            $this->components->warn('Laravel Passport is not installed — the OAuth transport needs it.');
            $this->components->bulletList([
                'Install it:  composer require laravel/passport',
                'Then re-run: php artisan mcp-kit:install --oauth',
            ]);

            return false;
        }

        // Publish (and wire-able) consent screen stub for the auth-code flow.
        $this->publish('mcp-kit-views', 'consent view');

        if ($this->getApplication()->has('passport:keys')) {
            $this->components->task('Generating Passport encryption keys', function () {
                $this->callSilently('passport:keys', array_filter([
                    '--force' => $this->option('force') ?: null,
                ]));

                return true;
            });
        } else {
            $this->components->warn('Skipped passport:keys — run it once Passport is fully registered.');
        }

        return true;
    }

    protected function installUi(): void
    {
        $this->publish('mcp-kit-ui', 'settings + token-management UI');

        $this->components->warn('The UI needs livewire/livewire and livewire/flux in your app.');
        $this->components->bulletList([
            'Route the settings page (gated on `manage-mcp`):',
            '  Route::get(\'/mcp-settings\', App\\Livewire\\McpSettings::class)->middleware([\'web\', \'auth\']);',
        ]);
    }

    protected function nextSteps(): void
    {
        $this->newLine();
        $this->components->info('Next steps');

        $steps = [
            'Define the gates your tools check (mcp-kit.view-tasks, mcp-kit.manage-tasks) '
                .'via Gate::define, a Policy, or a permission package.',
            'Issue a token for a user:  php artisan mcp-kit:token {email}',
        ];

        if ($this->option('oauth')) {
            $steps[] = 'Enable the OAuth transport:  set MCP_KIT_WEB_OAUTH_ENABLED=true in .env';
            $steps[] = 'Run your migrations:  php artisan migrate  '
                .'(the kit auto-loads Passport\'s oauth_* migrations).';
            $steps[] = 'Allow your connector\'s redirect host in config/mcp.php (redirect_domains), '
                .'e.g. https://claude.ai.';
        } else {
            $steps[] = 'Run your migrations:  php artisan migrate';
        }

        $this->components->bulletList($steps);

        if ($this->option('oauth')) {
            $this->newLine();
            $this->components->warn('Before going to production, read docs/06-deployment — the OAuth '
                .'flow also depends on infrastructure:');
            $this->components->bulletList([
                'Run `php artisan passport:keys` on EVERY environment. Missing keys let discovery '
                    .'and registration succeed but make token exchange 500 — a misleading failure.',
                'Your CDN/WAF must allow Claude\'s bots (ClaudeBot, Claude-User) on /mcp/*, /oauth/*, '
                    .'and /.well-known/* — AI-scraper blocking returns 403 at the edge.',
                'Your reverse proxy must serve /.well-known/* (do not deny dotfiles) and pass the real '
                    .'request URI to Laravel.',
            ]);
        }
    }
}
