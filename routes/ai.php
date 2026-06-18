<?php

use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Passport;

/*
|--------------------------------------------------------------------------
| MCP Kit AI routes
|--------------------------------------------------------------------------
|
| This file mirrors how a real app exposes an MCP server (compare with
| the production reference's routes/ai.php). The package loads it for you
| so the demo works out of the box, but in your OWN app you would copy
| this into your routes/ai.php and adjust the middleware.
|
*/

if (! config('mcp-kit.enabled', true)) {
    return;
}

// STDIO transport — Claude Code runs the server itself as a local process
// (`php artisan mcp:start mcp-kit`). Implicit OS-user trust, no auth.
if (config('mcp-kit.local.enabled', true)) {
    Mcp::local(config('mcp-kit.local.handle', 'mcp-kit'), TaskServer::class);
}

// Streamable HTTP transport — remote clients authenticate.
//
// Guard order matters: when you combine guards, put `sanctum` BEFORE
// `api` (Passport). Passport's token guard strips the Authorization
// header when a bearer token fails JWT validation, so a Sanctum token
// would never reach the sanctum guard if Passport ran first.
if (config('mcp-kit.web.enabled', true)) {
    $oauth = config('mcp-kit.web.oauth.enabled', false)
        && class_exists(Passport::class);

    // OAuth 2.1 discovery (RFC 9728 + RFC 8414) and Dynamic Client
    // Registration (RFC 7591), backed by Passport. Lets header-less
    // connectors (claude.ai) self-register and obtain a token.
    if ($oauth) {
        Mcp::oauthRoutes();

        // oauthRoutes() registers the two OAuth discovery documents but not
        // OpenID Connect discovery. Some connectors (and laravel/mcp's own
        // client) still probe /.well-known/openid-configuration; alias it to
        // the authorization-server metadata so hosts don't need a reverse-proxy
        // redirect. 308 preserves the request for clients that follow it.
        if (config('mcp-kit.web.oauth.openid_configuration', true)) {
            Route::get(
                '/.well-known/openid-configuration',
                fn () => redirect()->route('mcp.oauth.authorization-server', [], 308)
            )->name('mcp-kit.openid-configuration');
        }
    }

    // Compute the guard stack unless the host overrode it. With OAuth on
    // we accept either a Sanctum token or a Passport token — `sanctum`
    // first, for the header-stripping reason above.
    $middleware = config('mcp-kit.web.middleware') ?? [
        $oauth ? 'auth:sanctum,api' : 'auth:sanctum',
        'throttle:'.config('mcp-kit.web.throttle', '60,1'),
    ];

    Mcp::web(config('mcp-kit.web.path', 'mcp/tasks'), TaskServer::class)
        ->middleware($middleware)
        ->name('mcp-kit.tasks');
}
