<?php

use CleaniqueCoders\LaravelMcpKit\Support\McpToggle;

it('is enabled by default', function () {
    expect(McpToggle::enabled())->toBeTrue();
});

it('can be disabled and re-enabled at runtime', function () {
    McpToggle::disable();
    expect(McpToggle::enabled())->toBeFalse();

    McpToggle::enable();
    expect(McpToggle::enabled())->toBeTrue();
});

it('stays off while the env master switch is off, regardless of the toggle', function () {
    config()->set('mcp-kit.enabled', false);

    McpToggle::enable();

    expect(McpToggle::enabled())->toBeFalse();
});

it('toggles via the mcp-kit:toggle command', function () {
    $this->artisan('mcp-kit:toggle', ['state' => 'off'])->assertSuccessful();
    expect(McpToggle::enabled())->toBeFalse();

    $this->artisan('mcp-kit:toggle', ['state' => 'on'])->assertSuccessful();
    expect(McpToggle::enabled())->toBeTrue();
});

it('reports status and rejects an unknown state', function () {
    $this->artisan('mcp-kit:toggle', ['state' => 'status'])->assertSuccessful();
    $this->artisan('mcp-kit:toggle', ['state' => 'wat'])->assertFailed();
});
