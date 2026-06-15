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
    ],

    /*
    |--------------------------------------------------------------------------
    | Streamable HTTP (remote) transport
    |--------------------------------------------------------------------------
    |
    | The HTTP endpoint remote clients connect to. ALWAYS authenticated —
    | every tool is an agent-invokable endpoint, so the route is a new
    | attack surface. Use `auth:sanctum` for internal clients you own both
    | ends of; switch to `auth:sanctum,api` once you add Passport OAuth for
    | external clients (see the README).
    |
    */

    'web' => [
        'enabled' => env('MCP_KIT_WEB_ENABLED', true),
        'path' => env('MCP_KIT_WEB_PATH', 'mcp/tasks'),
        'middleware' => ['auth:sanctum', 'throttle:60,1'],
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
