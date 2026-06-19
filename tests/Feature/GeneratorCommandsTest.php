<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::deleteDirectory(app_path('Mcp'));
});

it('scaffolds a gate-first tool', function () {
    $this->artisan('mcp-kit:make-tool', ['name' => 'SendInvoiceTool'])->assertSuccessful();

    $path = app_path('Mcp/Tools/SendInvoiceTool.php');

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)
        ->toContain('extends McpKitTool')
        ->toContain("#[Name('send_invoice')]")
        ->toContain("return 'mcp-kit.send_invoice';")
        ->toContain('$this->authorizedUser($request)');
});

it('scaffolds an auth-gated resource', function () {
    $this->artisan('mcp-kit:make-resource', ['name' => 'BillingBoardResource'])->assertSuccessful();

    $path = app_path('Mcp/Resources/BillingBoardResource.php');

    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('extends Resource')->toContain('->can(');
});

it('scaffolds a runbook prompt', function () {
    $this->artisan('mcp-kit:make-prompt', ['name' => 'IncidentRunbookPrompt'])->assertSuccessful();

    $path = app_path('Mcp/Prompts/IncidentRunbookPrompt.php');

    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('extends Prompt')->toContain('human gate');
});
