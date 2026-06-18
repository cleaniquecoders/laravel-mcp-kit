<?php

/**
 * laravel/mcp's oauthRoutes() registers the OAuth discovery documents but not
 * OpenID Connect discovery. Some connectors (and laravel/mcp's own client)
 * probe /.well-known/openid-configuration anyway, so the kit aliases it to the
 * authorization-server metadata — no reverse-proxy redirect needed.
 */
it('aliases openid-configuration to the authorization-server discovery', function () {
    $this->get('/.well-known/openid-configuration')
        ->assertStatus(308)
        ->assertRedirect(route('mcp.oauth.authorization-server'));
});

it('serves the same metadata once the openid-configuration alias is followed', function () {
    $this->followingRedirects()
        ->get('/.well-known/openid-configuration')
        ->assertOk()
        ->assertJsonStructure([
            'issuer',
            'authorization_endpoint',
            'token_endpoint',
            'registration_endpoint',
            'code_challenge_methods_supported',
            'grant_types_supported',
        ]);
});
