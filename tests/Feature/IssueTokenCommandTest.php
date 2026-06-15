<?php

use CleaniqueCoders\LaravelMcpKit\Tests\Fixtures\User;

it('issues a token and prints the claude mcp add command', function () {
    User::create(['name' => 'Aisyah', 'email' => 'aisyah@example.test', 'grants' => ['view', 'manage']]);

    $this->artisan('mcp-kit:token', ['email' => 'aisyah@example.test'])
        ->expectsOutputToContain('Token issued')
        ->expectsOutputToContain('claude mcp add')
        ->expectsOutputToContain('Authorization: Bearer')
        ->assertSuccessful();

    expect(User::firstWhere('email', 'aisyah@example.test')->tokens()->count())->toBe(1);
});

it('uses the --name option as the token label', function () {
    User::create(['email' => 'farid@example.test', 'grants' => ['view']]);

    $this->artisan('mcp-kit:token', ['email' => 'farid@example.test', '--name' => 'laptop'])
        ->assertSuccessful();

    expect(User::firstWhere('email', 'farid@example.test')->tokens()->first()->name)->toBe('laptop');
});

it('fails when the user does not exist', function () {
    $this->artisan('mcp-kit:token', ['email' => 'ghost@example.test'])
        ->expectsOutputToContain('No user found')
        ->assertFailed();
});

it('refuses a user holding neither ability', function () {
    User::create(['email' => 'nobody@example.test', 'grants' => []]);

    $this->artisan('mcp-kit:token', ['email' => 'nobody@example.test'])
        ->expectsOutputToContain('neither the view-tasks nor the manage-tasks ability')
        ->assertFailed();

    expect(User::firstWhere('email', 'nobody@example.test')->tokens()->count())->toBe(0);
});

it('fails when the HTTP transport is disabled', function () {
    config()->set('mcp-kit.web.enabled', false);
    User::create(['email' => 'aisyah@example.test', 'grants' => ['view']]);

    $this->artisan('mcp-kit:token', ['email' => 'aisyah@example.test'])
        ->expectsOutputToContain('HTTP transport is disabled')
        ->assertFailed();
});
