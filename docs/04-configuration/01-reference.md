# Reference

`config/mcp-kit.php` covers the feature toggle, the STDIO handle, the HTTP path / throttle /
middleware, the OAuth block, and the ability names. Every value is env-overridable (`MCP_KIT_*`).

## Key environment variables

| Env | Default | Purpose |
|---|---|---|
| `MCP_KIT_ENABLED` | `true` | Master switch — when off, no routes are registered |
| `MCP_KIT_LOCAL_ENABLED` | `true` | Enable the STDIO transport |
| `MCP_KIT_LOCAL_HANDLE` | `mcp-kit` | The `mcp:start` handle |
| `MCP_KIT_LOCAL_USER` | `null` | Email the STDIO transport acts as (needed for gated tools over STDIO) |
| `MCP_KIT_WEB_ENABLED` | `true` | Enable the HTTP transport |
| `MCP_KIT_WEB_PATH` | `mcp/tasks` | HTTP endpoint path |
| `MCP_KIT_WEB_THROTTLE` | `60,1` | Rate limit on the HTTP endpoint (`maxAttempts,decayMinutes`) |
| `MCP_KIT_WEB_OAUTH_ENABLED` | `false` | Adds the OAuth 2.1 transport and the `api` guard |
| `MCP_KIT_OAUTH_ACCESS_HOURS` | `12` | OAuth access-token lifetime |
| `MCP_KIT_OAUTH_REFRESH_DAYS` | `30` | OAuth refresh-token lifetime |
| `MCP_KIT_OAUTH_AUTH_VIEW` | `mcp-kit::authorize` | Consent view (or `false` to keep Passport's default) |
| `MCP_KIT_OAUTH_LOAD_MIGRATIONS` | `true` | Auto-load Passport's `oauth_*` migrations |
| `MCP_KIT_OAUTH_OPENID_CONFIG` | `true` | Alias `/.well-known/openid-configuration` to the auth-server metadata |
| `MCP_KIT_TOGGLE_DEFAULT` | `true` | Default runtime-toggle state before it is ever set |
| `MCP_KIT_TOGGLE_STORE` | `null` | Cache store backing the runtime toggle (null = app default) |
| `MCP_KIT_TOGGLE_KEY` | `mcp-kit.runtime-enabled` | Cache key the toggle is stored under |
| `MCP_KIT_LOGS_PATH` | `storage/logs` | Directory the log tools read |
| `MCP_KIT_LOGS_MAX_LINES` | `500` | Hard cap on lines/entries a log tool returns |
| `MCP_KIT_EXPORT_DISK` | `local` | Disk exports are written to before signing a URL |
| `MCP_KIT_EXPORT_DIRECTORY` | `mcp-kit/exports` | Directory on that disk for export artifacts |
| `MCP_KIT_EXPORT_TTL` | `15` | Signed download URL lifetime (minutes) |
| `MCP_KIT_EXPORT_ROUTE` | `mcp-kit/exports` | URI prefix for the signed download route |
| `MCP_KIT_TOKEN_PREFIX` | `mcp-kit` | Name prefix scoping the MCP token tools |

> The runtime toggle layers **under** `MCP_KIT_ENABLED` (the deploy-time master switch). Use a shared
> cache store (redis/database/file, not `array`) so web, queue, and CLI processes agree. Flip it with
> `php artisan mcp-kit:toggle on|off|status`.

## Computed middleware

The HTTP middleware is computed automatically:

- `auth:sanctum` when OAuth is off,
- `auth:sanctum,api` when OAuth is on.

Set `web.middleware` to an explicit array to take full control of the stack.

## Abilities

The Gate abilities each tool checks. The **host app** is responsible for defining these gates (via
`Gate::define`, a Policy, or a permission package). They are listed in config for reference:

| Config key | Ability name | Used by |
|---|---|---|
| `abilities.view-tasks` | `mcp-kit.view-tasks` | task read tools |
| `abilities.manage-tasks` | `mcp-kit.manage-tasks` | task write tools |
| `abilities.view-logs` | `mcp-kit.view-logs` | `tail_logs`, `search_logs` |
| `abilities.export-logs` | `mcp-kit.export-logs` | `export_logs` |
| `abilities.view-jobs` | `mcp-kit.view-jobs` | `list_failed_jobs`, `queue_status` |
| `abilities.manage-jobs` | `mcp-kit.manage-jobs` | `retry_failed_job` |
| `abilities.view-system` | `mcp-kit.view-system` | `system_health`, `scheduled_tasks` |
| `abilities.view-audits` | `mcp-kit.view-audits` | `list_audits` |
| `abilities.view-activities` | `mcp-kit.view-activities` | `list_activities` |
| `abilities.view-permissions` | `mcp-kit.view-permissions` | RBAC tools |
| `abilities.manage-tokens` | `mcp-kit.manage-tokens` | MCP token tools |
| `abilities.manage-mcp` | `mcp-kit.manage-mcp` | runtime toggle UI/card |

`whoami` and `list_my_abilities` need only an authenticated user — they carry no specific ability so an
agent can always self-orient.

## Next Steps

- [OAuth 2.1](../03-authentication/02-oauth.md) — the OAuth config in context.
- [Getting Started](../01-getting-started/01-installation.md) — defining the gates.
