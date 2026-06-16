<?php

// config for CleaniqueCoders/LaravelMcpKit
return [

    /*
    |--------------------------------------------------------------------------
    | Feature toggle
    |--------------------------------------------------------------------------
    |
    | Master switch. When false, routes/ai.php registers nothing — the MCP
    | server effectively does not exist. Real apps gate MCP behind a flag
    | like this so it can be shipped dark and turned on per environment.
    |
    */

    'enabled' => env('MCP_KIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | STDIO (local) transport
    |--------------------------------------------------------------------------
    |
    | `php artisan mcp:start <handle>` runs the server over stdio for a
    | local client (Claude Code running it as a child process). Implicit
    | OS-user trust — no authentication layer.
    |
    */

    'local' => [
        'enabled' => env('MCP_KIT_LOCAL_ENABLED', true),
        'handle' => env('MCP_KIT_LOCAL_HANDLE', 'mcp-kit'),

        // The stdio transport has no auth layer, so tools have no token
        // holder to authorize. Set this to an email and local tools act as
        // that user (and their Gate abilities) — needed for `mcp:start` /
        // `mcp:inspector` to return data. Null keeps stdio tools gated.
        'user' => env('MCP_KIT_LOCAL_USER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Streamable HTTP (remote) transport
    |--------------------------------------------------------------------------
    |
    | The HTTP endpoint remote clients connect to. ALWAYS authenticated —
    | every tool is an agent-invokable endpoint, so the route is a new
    | attack surface.
    |
    | Two auth methods are supported on the SAME endpoint:
    |   - Sanctum personal access tokens (header clients: Claude Code/Desktop)
    |   - OAuth 2.1 via Passport (header-less connectors: claude.ai)
    |
    | When `oauth.enabled` is true the middleware becomes `auth:sanctum,api`
    | (Passport adds the `api` guard) and `Mcp::oauthRoutes()` is registered.
    | Otherwise it stays token-only (`auth:sanctum`). Set `middleware`
    | explicitly to override the computed default entirely.
    |
    */

    'web' => [
        'enabled' => env('MCP_KIT_WEB_ENABLED', true),
        'path' => env('MCP_KIT_WEB_PATH', 'mcp/tasks'),

        // Rate limiter applied to the endpoint, as "maxAttempts,decayMinutes".
        'throttle' => env('MCP_KIT_WEB_THROTTLE', '60,1'),

        // null => auto: ['auth:sanctum(,api)', 'throttle:<throttle>'].
        // Provide an array to take full control of the middleware stack.
        'middleware' => null,

        'oauth' => [
            // OFF by default — the kit works token-only out of the box.
            // Turn on once Passport is installed and configured (see docs).
            'enabled' => env('MCP_KIT_WEB_OAUTH_ENABLED', false),

            // Token lifetimes applied to Passport when OAuth is enabled.
            'access_token_hours' => (int) env('MCP_KIT_OAUTH_ACCESS_HOURS', 12),
            'refresh_token_days' => (int) env('MCP_KIT_OAUTH_REFRESH_DAYS', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Abilities
    |--------------------------------------------------------------------------
    |
    | The Gate abilities each tool checks. The HOST app is responsible for
    | defining these gates (via Gate::define, a Policy, or a permission
    | package like spatie/laravel-permission). Listed here for reference.
    |
    */

    'abilities' => [
        'view-tasks' => 'mcp-kit.view-tasks',
        'manage-tasks' => 'mcp-kit.manage-tasks',
    ],

];
