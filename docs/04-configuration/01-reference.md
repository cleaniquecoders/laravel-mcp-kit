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

## Computed middleware

The HTTP middleware is computed automatically:

- `auth:sanctum` when OAuth is off,
- `auth:sanctum,api` when OAuth is on.

Set `web.middleware` to an explicit array to take full control of the stack.

## Abilities

The Gate abilities each tool checks. The **host app** is responsible for defining these gates (via
`Gate::define`, a Policy, or a permission package). They are listed in config for reference:

| Config key | Ability name |
|---|---|
| `abilities.view-tasks` | `mcp-kit.view-tasks` |
| `abilities.manage-tasks` | `mcp-kit.manage-tasks` |

## Next Steps

- [OAuth 2.1](../03-authentication/02-oauth.md) — the OAuth config in context.
- [Getting Started](../01-getting-started/01-installation.md) — defining the gates.
