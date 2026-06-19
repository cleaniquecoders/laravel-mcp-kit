<?php

use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;
use CleaniqueCoders\LaravelMcpKit\Tools\IssueMcpTokenTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListMcpTokensTool;
use CleaniqueCoders\LaravelMcpKit\Tools\RevokeMcpTokenTool;

it('issues an MCP-scoped token for the authenticated user', function () {
    $user = admin();

    TaskServer::actingAs($user)
        ->tool(IssueMcpTokenTool::class, ['name' => 'laptop'])
        ->assertOk()
        ->assertSee('mcp-kit:laptop')
        ->assertSee('"token"');

    expect($user->tokens()->where('name', 'mcp-kit:laptop')->exists())->toBeTrue();
});

it('blocks a user without the manage-tokens ability from issuing', function () {
    TaskServer::actingAs(nobody())
        ->tool(IssueMcpTokenTool::class, ['name' => 'nope'])
        ->assertHasErrors();
});

it('lists only the MCP-prefixed tokens, never other app tokens', function () {
    $user = admin();
    $user->createToken('mcp-kit:laptop');
    $user->createToken('mobile-app'); // a non-MCP token

    TaskServer::actingAs($user)
        ->tool(ListMcpTokensTool::class)
        ->assertOk()
        ->assertSee('mcp-kit:laptop')
        ->assertDontSee('mobile-app');
});

it('revokes one of the user\'s MCP tokens by id', function () {
    $user = admin();
    $token = $user->createToken('mcp-kit:laptop')->accessToken;

    TaskServer::actingAs($user)
        ->tool(RevokeMcpTokenTool::class, ['id' => $token->getKey()])
        ->assertOk()
        ->assertSee('revoked');

    expect($user->tokens()->count())->toBe(0);
});

it('refuses to revoke a non-MCP token', function () {
    $user = admin();
    $token = $user->createToken('mobile-app')->accessToken;

    TaskServer::actingAs($user)
        ->tool(RevokeMcpTokenTool::class, ['id' => $token->getKey()])
        ->assertHasErrors();

    expect($user->tokens()->count())->toBe(1);
});
