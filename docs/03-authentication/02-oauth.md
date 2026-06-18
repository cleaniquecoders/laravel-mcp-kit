# OAuth 2.1 (Passport)

For connectors that **cannot** send custom headers (claude.ai). The client discovers the server,
self-registers (Dynamic Client Registration), and runs an authorization-code + PKCE flow.

OAuth is **off by default** — the kit works token-only out of the box.

## Setup

### 1. Install Passport and run the OAuth installer

The installer publishes the consent view and generates the Passport keys for you:

```bash
composer require laravel/passport
php artisan mcp-kit:install --oauth
```

### 2. Turn on the OAuth transport and migrate

```dotenv
MCP_KIT_WEB_OAUTH_ENABLED=true
```

```bash
php artisan migrate
```

With the flag on, the package does the rest **automatically**:

- registers `Mcp::oauthRoutes()` (discovery + Dynamic Client Registration),
- switches the endpoint middleware to `auth:sanctum,api`,
- auto-wires an `api` (Passport) guard — only if you haven't defined one,
- **loads Passport's `oauth_*` migrations** (so a plain `migrate` is enough — no
  `vendor:publish --tag=passport-migrations`),
- **wires the consent screen** (`mcp-kit::authorize`) — no service-provider edit needed.

### 3. Allow the connector's redirect domains

In the published `config/mcp.php`:

```php
'redirect_domains' => ['https://claude.ai', 'https://claude.com', 'http://localhost'],
```

### 4. Connect

No header needed; Claude drives the OAuth flow:

```bash
claude mcp add --transport http mcp-kit https://your-app.test/mcp/tasks
```

## Customising

| Config | Default | Purpose |
|---|---|---|
| `mcp-kit.web.oauth.authorization_view` | `mcp-kit::authorize` | Point at your own Blade view to brand the consent screen, or `false` to keep Passport's default |
| `mcp-kit.web.oauth.load_migrations` | `true` | Set `false` to publish and own the `oauth_*` migrations yourself |
| `mcp-kit.web.oauth.access_token_hours` | `12` | Access-token lifetime |
| `mcp-kit.web.oauth.refresh_token_days` | `30` | Refresh-token lifetime |

Both auto-wirings are env-overridable.

## Gotchas

### Guard order matters: `sanctum` before `api`

Passport's token guard strips the `Authorization` header when a bearer token fails JWT validation, so
a Sanctum token would never reach the sanctum guard if Passport ran first. The computed middleware
(`auth:sanctum,api`) already gets this right.

### One token trait per model

`Sanctum\HasApiTokens` and `Passport\HasApiTokens` cannot coexist on the same model. Use **only** the
Sanctum trait — Passport's guard calls `withAccessToken()` itself.

## Production

The application-side setup above is the easy part. In production the OAuth flow also has to survive
your CDN and reverse proxy, and Passport needs encryption keys on every environment. See
[MCP OAuth in Production](../06-deployment/01-mcp-oauth-production.md) for the operational checklist —
it is the difference between "works on my machine" and "claude.ai can actually connect".

## Next Steps

- [MCP OAuth in Production](../06-deployment/01-mcp-oauth-production.md) — deploy checklist.
- [Try it locally](../05-development/01-workbench.md) — exercise the full OAuth flow in the Workbench.
- [Configuration](../04-configuration/README.md) — every `MCP_KIT_*` variable.
