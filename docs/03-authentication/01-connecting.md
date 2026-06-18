# Connecting

The package exposes two transports. Pick whichever your client can use.

## STDIO (local — Claude Code runs the server itself)

```bash
claude mcp add mcp-kit -- php artisan mcp:start mcp-kit
```

No authentication layer: implicit OS-user trust. Best for local development.

> Because STDIO has no token holder, the gated tools need a user to authorize against. Set
> `MCP_KIT_LOCAL_USER` to the email of the user the local transport should act as (and inherit the
> abilities of). Without it, the tools return *unauthorized* over STDIO.
>
> ```dotenv
> MCP_KIT_LOCAL_USER=you@example.com
> ```

## Streamable HTTP (remote — authenticated)

The package registers an authenticated HTTP endpoint at `POST /mcp/tasks` (configurable). It supports
**two auth methods on the same endpoint**.

### Method 1 — personal access token (Sanctum)

For clients that can send a custom header (Claude Code / Desktop). Issue a token for a user who holds
the abilities:

```bash
php artisan mcp-kit:token user@example.com --name="my-laptop"
```

The command prints a ready-to-paste command:

```bash
claude mcp add --transport http mcp-kit https://your-app.test/mcp/tasks \
  --header "Authorization: Bearer <token>"
```

### Method 2 — OAuth 2.1 (Passport)

For connectors that **cannot** send custom headers (claude.ai). See [OAuth 2.1](02-oauth.md) for the
full setup.

## Token management UI (optional)

A self-service Livewire + Flux page manages **both** auth methods (generate/revoke Sanctum tokens and
disconnect OAuth apps). It ships as a publishable stub and requires `livewire/livewire` and
`livewire/flux` in your app:

```bash
php artisan vendor:publish --tag="mcp-kit-ui"
# or: php artisan mcp-kit:install --ui
```

This publishes `app/Livewire/McpTokens.php` and `resources/views/livewire/mcp-tokens.blade.php` — wire
up a route and restyle to match your app.

## Next Steps

- [OAuth 2.1](02-oauth.md) — enable the header-less flow.
- [Configuration](../04-configuration/README.md) — middleware and throttle reference.
