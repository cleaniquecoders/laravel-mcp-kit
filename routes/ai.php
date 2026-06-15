<?php

use CleaniqueCoders\LaravelMcpKit\Servers\TaskServer;
use Laravel\Mcp\Facades\Mcp;

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
    Mcp::web(config('mcp-kit.web.path', 'mcp/tasks'), TaskServer::class)
        ->middleware(config('mcp-kit.web.middleware', ['auth:sanctum', 'throttle:60,1']))
        ->name('mcp-kit.tasks');
}
