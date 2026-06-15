<?php

use CleaniqueCoders\LaravelMcpKit\Tests\Fixtures\User;

it('still authenticates a Sanctum token under the dual guard (sanctum before api)', function () {
    // With OAuth on, the endpoint runs `auth:sanctum,api`. A Sanctum token
    // must still pass — this is the regression the guard order prevents.
    $user = User::create(['email' => 'aisyah@example.test', 'grants' => ['view', 'manage']]);
    $token = $user->createToken('mcp-kit')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('mcp-kit.tasks'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);

    expect($response->status())->not->toBe(401);
});

it('uses the dual guard middleware when OAuth is enabled', function () {
    $route = app('router')->getRoutes()->getByName('mcp-kit.tasks');

    expect($route->gatherMiddleware())->toContain('auth:sanctum,api');
});

it('serves the protected-resource discovery metadata', function () {
    $this->getJson('/.well-known/oauth-protected-resource')
        ->assertOk()
        ->assertJsonStructure(['resource', 'authorization_servers', 'scopes_supported'])
        ->assertJsonPath('scopes_supported', ['mcp:use']);
});

it('serves the authorization-server discovery metadata', function () {
    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonStructure([
            'issuer',
            'authorization_endpoint',
            'token_endpoint',
            'registration_endpoint',
            'code_challenge_methods_supported',
            'grant_types_supported',
        ])
        ->assertJsonPath('code_challenge_methods_supported', ['S256'])
        ->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token']);
});

it('lets an allowed client self-register via dynamic client registration', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])
        ->assertCreated()
        ->assertJsonPath('scope', 'mcp:use')
        ->assertJsonPath('token_endpoint_auth_method', 'none')
        ->assertJsonStructure(['client_id', 'grant_types', 'redirect_uris']);
});

it('rejects dynamic client registration from a disallowed redirect domain', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Sketchy',
        'redirect_uris' => ['https://evil.example.com/callback'],
    ])
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_redirect_uri');
});

it('registers the api (passport) guard when OAuth is enabled', function () {
    expect(config('auth.guards.api'))->toBe([
        'driver' => 'passport',
        'provider' => 'users',
    ]);
});
