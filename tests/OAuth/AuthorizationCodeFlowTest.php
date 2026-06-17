<?php

use CleaniqueCoders\LaravelMcpKit\Tests\Fixtures\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Artisan;

/**
 * The full OAuth 2.1 authorization-code + PKCE flow, end to end: a header-less
 * connector (claude.ai) self-registers, the user approves the kit's consent
 * screen, the code is exchanged for a token, and that token authenticates a
 * real call against the MCP endpoint. This is the path discovery + DCR only
 * set up — here we prove a Passport-issued token actually opens the door.
 */
beforeEach(function () {
    // Token signing/validation needs the Passport keys. Generate them into the
    // testbench storage path for the duration of the suite.
    Artisan::call('passport:keys', ['--force' => true]);

    // The browser flow posts without a CSRF token; the OAuth issuance is what
    // we're exercising, not Laravel's CSRF guard.
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

it('issues a token through the consent flow that authenticates the MCP endpoint', function () {
    $user = User::create(['email' => 'imran@example.test', 'grants' => ['view', 'manage']]);

    // 1. The connector self-registers (Dynamic Client Registration) — a public
    //    client, so the flow is PKCE with no client secret.
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])->assertCreated()->json('client_id');

    // 2. PKCE pair.
    $verifier = str_repeat('mcp-kit-pkce-verifier-0123456789', 2); // 64 chars, [43,128]
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $redirectUri = 'https://claude.ai/api/mcp/auth_callback';

    // 3. The signed-in user is shown the kit's consent screen (mcp-kit::authorize,
    //    auto-wired by the provider — no host service-provider edit).
    $this->actingAs($user)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'opaque-state',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertSee('Authorization Request')
        ->assertSee('Claude');

    // The consent screen carries an auth_token Passport matches against the
    // session when the user approves.
    $authToken = session('authToken');

    // 4. Approve — Passport completes the request from the session and redirects
    //    back to the connector with the authorization code.
    $location = $this->post('/oauth/authorize', ['auth_token' => $authToken])
        ->assertRedirect()
        ->headers->get('Location');

    parse_str(parse_url($location, PHP_URL_QUERY), $callback);

    expect($callback)->toHaveKey('code')
        ->and($callback['state'])->toBe('opaque-state');

    // 5. Exchange the code for an access token (PKCE verifier, no secret).
    $accessToken = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'code_verifier' => $verifier,
        'code' => $callback['code'],
    ])->assertOk()->json('access_token');

    expect($accessToken)->toBeString()->not->toBeEmpty();

    // 6. The Passport token authenticates the MCP endpoint under the dual guard.
    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->postJson(route('mcp-kit.tasks'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
        ->assertOk();
});

it('denies the request when the user cancels the consent screen', function () {
    $user = User::create(['email' => 'lina@example.test', 'grants' => ['view']]);

    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])->assertCreated()->json('client_id');

    $verifier = str_repeat('mcp-kit-pkce-verifier-0123456789', 2);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $this->actingAs($user)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'opaque-state',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk();

    $authToken = session('authToken');

    // Cancelling redirects back with an access_denied error and no code.
    $location = $this->delete('/oauth/authorize', ['auth_token' => $authToken])
        ->assertRedirect()
        ->headers->get('Location');

    parse_str(parse_url($location, PHP_URL_QUERY), $callback);

    expect($callback)->not->toHaveKey('code')
        ->and($callback['error'])->toBe('access_denied');
});
