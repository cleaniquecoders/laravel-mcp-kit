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
            // Once Passport is installed, flipping this on is all it takes:
            // the kit auto-loads Passport's migrations and wires the consent
            // screen for you (see the two keys below). No service-provider
            // edits, no extra publish steps. See `mcp-kit:install --oauth`.
            'enabled' => env('MCP_KIT_WEB_OAUTH_ENABLED', false),

            // Token lifetimes applied to Passport when OAuth is enabled.
            'access_token_hours' => (int) env('MCP_KIT_OAUTH_ACCESS_HOURS', 12),
            'refresh_token_days' => (int) env('MCP_KIT_OAUTH_REFRESH_DAYS', 30),

            // Consent screen Passport renders during the auth-code flow.
            // Passport 13 ships none, so the kit wires its own publishable
            // stub (`mcp-kit::authorize`) for you. Point this at your own
            // Blade view to brand it, or set it to false to leave Passport's
            // default untouched and wire the view yourself.
            'authorization_view' => env('MCP_KIT_OAUTH_AUTH_VIEW', 'mcp-kit::authorize'),

            // Passport 13 no longer auto-loads its oauth_* migrations. With
            // this on (default) the kit registers them so a plain `migrate`
            // creates the tables — no `vendor:publish --tag=passport-migrations`
            // needed. Turn off if you publish/own those migrations yourself.
            'load_migrations' => (bool) env('MCP_KIT_OAUTH_LOAD_MIGRATIONS', true),

            // laravel/mcp's oauthRoutes() registers the two OAuth discovery
            // documents but not OpenID Connect discovery. Some connectors (and
            // laravel/mcp's own client) still probe
            // /.well-known/openid-configuration. With this on (default) the kit
            // aliases it to the authorization-server metadata so hosts don't
            // need a reverse-proxy redirect. Turn off if your edge serves it.
            'openid_configuration' => (bool) env('MCP_KIT_OAUTH_OPENID_CONFIG', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Abilities
    |--------------------------------------------------------------------------
    |
    | The Gate abilities each tool checks. The HOST app is responsible for
    | defining these gates (via Gate::define, a Policy, or a permission
    | package like spatie/laravel-permission). They are listed here so a tool
    | reads its ability from config — letting a host remap any tool onto its
    | own permission scheme without touching the tool class.
    |
    */

    'abilities' => [
        // Task demo (Tier 0 — the reference domain).
        'view-tasks' => 'mcp-kit.view-tasks',
        'manage-tasks' => 'mcp-kit.manage-tasks',

        // Generic ops tools (Tier 1). All zero-domain-coupling.
        'view-logs' => 'mcp-kit.view-logs',
        'export-logs' => 'mcp-kit.export-logs',
        'view-jobs' => 'mcp-kit.view-jobs',
        'manage-jobs' => 'mcp-kit.manage-jobs',
        'view-system' => 'mcp-kit.view-system',

        // Package-gated tools (Tier 2). Only used when the backing package
        // is installed — the tool stays unregistered otherwise.
        'view-audits' => 'mcp-kit.view-audits',
        'view-activities' => 'mcp-kit.view-activities',
        'view-permissions' => 'mcp-kit.view-permissions',
        'manage-tokens' => 'mcp-kit.manage-tokens',

        // Infrastructure (Tier 3): who may flip the runtime MCP toggle.
        'manage-mcp' => 'mcp-kit.manage-mcp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime toggle
    |--------------------------------------------------------------------------
    |
    | A cache-backed on/off switch independent of MCP_KIT_ENABLED (which stays
    | the deploy-time master kill-switch). It lets an operator turn MCP off
    | without a redeploy — see Support\McpToggle and the `mcp-kit:toggle`
    | command. The toggle layers UNDER the env switch: if MCP_KIT_ENABLED is
    | false, MCP is off regardless of the toggle.
    |
    | Because routes/ai.php reads the toggle when it registers the server, the
    | toggle clears the route cache on change so the next request re-evaluates.
    | Use a SHARED cache store (redis/database/file — not `array`) so the flag
    | is visible across web + queue + CLI processes.
    |
    */

    'toggle' => [
        // Default state when the flag has never been set.
        'default' => (bool) env('MCP_KIT_TOGGLE_DEFAULT', true),

        // Cache store backing the flag. null => the app's default store.
        'store' => env('MCP_KIT_TOGGLE_STORE'),

        // Cache key the flag is stored under.
        'key' => env('MCP_KIT_TOGGLE_KEY', 'mcp-kit.runtime-enabled'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ops tools
    |--------------------------------------------------------------------------
    |
    | Settings for the generic ops tools (logs, exports). Each tool degrades
    | gracefully when its backing storage is absent.
    |
    */

    'ops' => [
        'logs' => [
            // Directory the log tools read, relative to storage/ unless
            // absolute. Defaults to Laravel's storage/logs.
            'path' => env('MCP_KIT_LOGS_PATH', storage_path('logs')),

            // Hard cap on how many lines a single tail/search call returns,
            // so a huge log can never blow up a response.
            'max_lines' => (int) env('MCP_KIT_LOGS_MAX_LINES', 500),
        ],

        'export' => [
            // Filesystem disk exports are written to before signing a URL.
            'disk' => env('MCP_KIT_EXPORT_DISK', 'local'),

            // Directory on that disk for export artifacts.
            'directory' => env('MCP_KIT_EXPORT_DIRECTORY', 'mcp-kit/exports'),

            // Lifetime of the signed download URL, in minutes.
            'ttl' => (int) env('MCP_KIT_EXPORT_TTL', 15),

            // URI prefix for the signed download route.
            'route' => env('MCP_KIT_EXPORT_ROUTE', 'mcp-kit/exports'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP token management (Tier 2 — requires laravel/sanctum)
    |--------------------------------------------------------------------------
    |
    | The token tools (issue/list/revoke_mcp_token) only ever touch tokens
    | whose name starts with this prefix, so an agent can manage its own MCP
    | connections without being able to see or revoke a user's other app
    | tokens. They also only ever act on the AUTHENTICATED user's own tokens.
    |
    */

    'tokens' => [
        'prefix' => env('MCP_KIT_TOKEN_PREFIX', 'mcp-kit'),
    ],

];
