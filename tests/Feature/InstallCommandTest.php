<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    // The command publishes into the shared testbench skeleton — tidy every
    // artifact so it never collides with the workbench's own migrations.
    File::delete(config_path('mcp-kit.php'));
    File::deleteDirectory(resource_path('views/vendor/mcp-kit'));

    foreach (File::glob(database_path('migrations/*_create_mcp_kit_tasks_table.php')) as $migration) {
        File::delete($migration);
    }
});

it('publishes the config and prints the post-install steps', function () {
    expect(File::exists(config_path('mcp-kit.php')))->toBeFalse();

    $this->artisan('mcp-kit:install')
        ->expectsOutputToContain('Installing the Laravel MCP Kit')
        ->expectsOutputToContain('Next steps')
        ->assertSuccessful();

    expect(File::exists(config_path('mcp-kit.php')))->toBeTrue();
});

it('reminds the host to run migrations on a token-only install', function () {
    $this->artisan('mcp-kit:install')
        ->expectsOutputToContain('php artisan migrate')
        ->assertSuccessful();
});

it('surfaces the OAuth enable flag when installing the oauth transport', function () {
    // Passport is present in the dev deps, so the --oauth branch runs for real
    // (publishes the consent view, generates keys) — all idempotent.
    $this->artisan('mcp-kit:install --oauth')
        ->expectsOutputToContain('MCP_KIT_WEB_OAUTH_ENABLED=true')
        ->assertSuccessful();
});
