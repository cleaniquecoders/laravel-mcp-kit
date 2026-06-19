<?php

use CleaniqueCoders\LaravelMcpKit\Support\McpToggle;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Workbench\App\Livewire\McpSettings;

// The settings page is a workbench harness for issue #16. Boot Livewire and the
// workbench view path so the component renders inside the package test app.
beforeEach(function () {
    $this->app->register(LivewireServiceProvider::class);
    View::addLocation(dirname(__DIR__, 2).'/workbench/resources/views');
    Livewire::component('mcp-settings', McpSettings::class);
});

it('renders the settings panels and toggles MCP for a manage-mcp user', function () {
    $this->actingAs(admin());

    expect(McpToggle::enabled())->toBeTrue();

    Livewire::test(McpSettings::class)
        ->assertOk()
        ->assertSee('Effective configuration')
        ->assertSee('Registered tools')
        ->assertSee('Health')
        ->call('disable');

    expect(McpToggle::enabled())->toBeFalse();
});

it('forbids a user without the manage-mcp ability', function () {
    $this->actingAs(nobody());

    Livewire::test(McpSettings::class)->assertForbidden();
});
