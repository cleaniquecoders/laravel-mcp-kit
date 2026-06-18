# MCP OAuth in Production

The application-side OAuth setup is one flag (see [OAuth 2.1](../03-authentication/02-oauth.md)). What
actually breaks in production is everything *in front of* Laravel: encryption keys, your CDN, and your
reverse proxy. A header-less connector like claude.ai calls your server from the cloud and must reach
three things untouched — discovery, dynamic client registration, and the token endpoint.

This guide is vendor-neutral. The nginx and CDN snippets are **reference examples** — adapt them to
your stack.

## The flow you must keep working

```text
POST /mcp/tasks                       → 401 + WWW-Authenticate
GET  /.well-known/oauth-protected-resource
GET  /.well-known/oauth-authorization-server
POST /oauth/register                  → Dynamic Client Registration (PKCE, no secret)
GET  /oauth/authorize                 → login + consent screen
POST /oauth/token                     → access token
```

The connector runs from Anthropic's cloud, so **every** one of these paths must survive your CDN and
reverse proxy. Discovery and registration succeeding while token exchange fails is the most common —
and most misleading — symptom.

## 1. Generate Passport keys per environment

Passport signs tokens with RSA keys that are **not** in version control. Generate them once on each
environment (production, staging) after deploy:

```bash
php artisan passport:keys
```

> **Failure signature**: if the keys are missing, **discovery and DCR still return `200`** — only
> `POST /oauth/token` fails with a `500`. The connector appears to "almost work", which hides the real
> cause. If token exchange 500s, check for `storage/oauth-private.key` first.

`mcp-kit:install --oauth` runs `passport:keys` for you, but it is a per-environment step — re-run it
(or bake it into your deploy hook **once**) on every server.

## 2. Let the connector's traffic through your CDN / WAF

Claude calls your endpoints from the cloud with bot user-agents (`ClaudeBot`, `Claude-User`,
`python-httpx`). A CDN with AI-bot blocking enabled (e.g. Cloudflare's "Block AI Scrapers and
Crawlers") returns `403` at the edge — before the request ever reaches Laravel.

| Path | Must be reachable by | Why |
|---|---|---|
| `/mcp/tasks` | Claude bots | The MCP endpoint itself |
| `/oauth/*` | Claude bots | Registration, authorize, token |
| `/.well-known/*` | Claude bots | Discovery metadata |

> **Tip**: A blanket "skip WAF" rule is unreliable. Prefer turning the AI-bot block **off** for these
> path prefixes specifically, or add a managed-rule exception scoped to `/mcp/*`, `/oauth/*`, and
> `/.well-known/*`.

## 3. Serve `/.well-known/*` correctly at the reverse proxy

Two origin-level traps break discovery even when Laravel is configured correctly:

1. Some panels (RunCloud, others) **deny dotfile paths** by default, so `/.well-known/...` returns
   `403` before PHP runs.
2. A custom `try_files … /index.php` location can **drop the request URI**, so Laravel receives `/`
   instead of the discovery path.

The robust fix is to serve the discovery documents as **static JSON** with an exact prefix match, and
to alias the issuer-relative paths some clients build. Reference nginx:

```nginx
# Serve discovery metadata as static JSON (^~ wins over regex/PHP locations).
location ^~ /.well-known/oauth-authorization-server {
    default_type application/json;
    return 200 '{"issuer":"https://your-app.example","authorization_endpoint":"https://your-app.example/oauth/authorize","token_endpoint":"https://your-app.example/oauth/token","registration_endpoint":"https://your-app.example/oauth/register","code_challenge_methods_supported":["S256"],"grant_types_supported":["authorization_code","refresh_token"]}';
}

# Issuer-relative endpoints some clients build when discovery is incomplete.
# 308 preserves method + body when redirecting to the Passport routes.
location = /authorize { return 308 /oauth/authorize; }
location = /token     { return 308 /oauth/token; }
```

Note that `/.well-known/openid-configuration` is handled **by the package** — it aliases to the
authorization-server metadata automatically (toggle with `MCP_KIT_OAUTH_OPENID_CONFIG`), so you do not
need an nginx redirect for it. `laravel/mcp` registers the two OAuth discovery documents; the kit adds
the OpenID one some connectors probe.

> **Note**: Prefer proxying these paths to Laravel so the package's discovery output stays the single
> source of truth. Only fall back to static JSON when your panel mangles dotfile paths or the URI.
> Either way, keep the `issuer` and endpoint URLs **HTTPS** and matching your real domain.

## 4. Route caching is safe

`php artisan route:cache` is compatible with the OAuth transport — the Passport and discovery routes
cache fine. The package guards its route registration on the route cache, so a cached HTTP route table
is not mutated at boot.

## Production checklist

- [ ] `php artisan passport:keys` run on every environment
- [ ] CDN/WAF allows Claude bots on `/mcp/*`, `/oauth/*`, `/.well-known/*`
- [ ] `/.well-known/oauth-authorization-server` returns `200` JSON from the public URL
- [ ] `/.well-known/oauth-protected-resource` returns `200` JSON from the public URL
- [ ] `POST /oauth/register` returns `201` from the public URL
- [ ] All issuer/endpoint URLs are HTTPS and match the real domain
- [ ] `redirect_domains` in `config/mcp.php` includes `https://claude.ai`

## Next Steps

- [OAuth 2.1](../03-authentication/02-oauth.md) — the application-side wiring.
- [Configuration](../04-configuration/01-reference.md) — the env vars used above.
