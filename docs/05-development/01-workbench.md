# Workbench

The package ships a runnable skeleton app under `workbench/` so you can exercise the server — both
transports and **both** auth methods — without a host app.

## Boot it

```bash
composer serve   # one command: recreate sqlite + migrate + seed + Passport keys,
                 # register the demo users in Claude, then boot on 127.0.0.1:8000
```

`composer serve` chains everything for you:

1. `build-db` — recreate the sqlite file, `migrate:fresh` (auto-seeds via `testbench.yaml`),
   `passport:keys`.
2. `mcp-connect` — issue a fresh token per seeded user and (re)register each in Claude as
   `mcp-kit-manager` / `mcp-kit-viewer`. Non-fatal: if the `claude` CLI is absent it prints the manual
   command and still boots.
3. `serve.sh` — bind a **fixed** host/port (`MCP_KIT_HOST` / `MCP_KIT_PORT`, default
   `127.0.0.1:8000`). It fails rather than drifting to another port, so the registered URL is always
   correct. Override with `MCP_KIT_PORT=9000 composer serve`.

Seeded users: `manager@example.com` (read + write) and `viewer@example.com` (read only). The workbench
sets `MCP_KIT_LOCAL_USER=manager@example.com`, so the STDIO tools work too.

## Helper scripts

```bash
composer mcp-token <email>   # issue one token (prints the claude mcp add command)
composer mcp-tokens          # issue tokens for both demo users
composer mcp-connect         # (re)register the demo users in Claude
composer mcp-inspect         # open the MCP Inspector (browser UI) against the stdio server
composer mcp-inspect-web     # open the MCP Inspector against the HTTP endpoint (paste a Bearer token)
```

## Exercise it by hand

```bash
# HTTP (needs a Bearer token; no token → 401)
TOKEN=$(vendor/bin/testbench mcp-kit:token manager@example.com --only-token)
curl -X POST http://127.0.0.1:8000/mcp/tasks \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json, text/event-stream" -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"curl","version":"1"}}}'

# OAuth — discovery + dynamic client registration are live:
curl http://127.0.0.1:8000/.well-known/oauth-authorization-server
curl -X POST http://127.0.0.1:8000/oauth/register \
  -H "Content-Type: application/json" \
  -d '{"client_name":"Claude","redirect_uris":["https://claude.ai/api/mcp/auth_callback"]}'
```

For the full OAuth browser flow, visit `/login` first (a demo-only auto-login) so Passport's consent
screen has a session.

## MCP settings UI

The MCP settings & operations page ([#16](https://github.com/cleaniquecoders/laravel-mcp-kit/issues/16))
ships two ways: a publishable Flux component for host apps (`mcp-kit:install --ui` →
`App\Livewire\McpSettings`, route it yourself), and this build-free workbench preview (Tailwind via CDN)
for clicking through it without a build:

```bash
composer serve
# then in a browser just open the settings page — it auto-signs you in as the
# demo manager (holds `manage-mcp`) via the guest→/login→back bounce:
open http://127.0.0.1:8000/mcp
```

It flips the runtime toggle (the real `Support\McpToggle`) and shows the effective config, health, and
registered tools — gated on `manage-mcp`. Both surfaces read the same services (`Support\McpConfigSnapshot`,
`Support\SystemHealth`), so the preview and the shipped UI never drift. See
`workbench/app/Livewire/McpSettings.php` and `stubs/Livewire/McpSettings.php.stub`.

The runtime toggle and a live system-health readout:

![Toggle and health](../../art/mcp-settings-overview.png)

The read-only effective configuration and the auto-registered tool catalogue (Tier-2 tools flagged
`·gated`):

![Effective config and registered tools](../../art/mcp-settings-config.png)

The ability map each tool checks (defined by the host's permission system):

![Abilities](../../art/mcp-settings-abilities.png)

## Next Steps

- [Testing](02-testing.md) — run the suite.
- [OAuth 2.1](../03-authentication/02-oauth.md) — the flow the Workbench demonstrates.
