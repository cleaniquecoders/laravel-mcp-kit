# Laravel MCP Kit

[![Tests](https://img.shields.io/badge/tests-30%20passing-brightgreen)](.)
[![Laravel MCP](https://img.shields.io/badge/laravel%2Fmcp-%5E0.8-orange)](https://github.com/laravel/mcp)

A **reusable starter package** for adding a Model Context Protocol (MCP) server to your Laravel
projects, built on the official [`laravel/mcp`](https://github.com/laravel/mcp) package. It ships a
small task-management domain as a working reference and gives you, ready to copy or extend, the
patterns a production MCP server needs:

- **Tools** (read + write), **Resources** (read-by-URI context), and **Prompts** (reusable templates)
- **Per-tool authorization** via Gate abilities — MCP is a third UI, never a back door
- **uuid-only** inputs/outputs — the internal auto-increment id never leaks to the agent
- **Write tools funnel through Action classes** — the agent, the web UI and the CLI share one set of business rules
- **STDIO** (local) and **Streamable HTTP** (remote) transports
- **Two HTTP auth methods** — Sanctum personal access tokens *and* OAuth 2.1 (Passport) — on one endpoint
- **Honest annotations** (`#[IsReadOnly]`) so clients know which tools change state and gate them

---

## Requirements

- PHP 8.4+
- Laravel 11, 12, or 13
- `laravel/mcp` ^0.8

## Installation

```bash
composer require cleaniquecoders/laravel-mcp-kit
```

Run the installer — it publishes the config and migration in one step:

```bash
php artisan mcp-kit:install          # token-only (Sanctum) transport
php artisan mcp-kit:install --oauth  # also wire the OAuth 2.1 flow (see below)
php artisan migrate
```

> Prefer to do it by hand? `vendor:publish --tag="mcp-kit-config"` and
> `--tag="mcp-kit-migrations"` publish the same files.

Seed some demo tasks for the agent to query:

```bash
php artisan mcp-kit:demo
```

## Define the gates (required)

Every tool checks a Gate ability. The package does **not** ship a permission system — define the two
abilities however your app does authorization (a Policy, `Gate::define`, or `spatie/laravel-permission`).
The simplest possible wiring, in a service provider:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('mcp-kit.view-tasks', fn ($user) => $user->isStaff());
Gate::define('mcp-kit.manage-tasks', fn ($user) => $user->isAdmin());
```

| Ability | Used by |
|---|---|
| `mcp-kit.view-tasks` | `list_tasks`, `get_task`, `task_board` resource |
| `mcp-kit.manage-tasks` | `create_task`, `complete_task`, `assign_task` |

## What it exposes

**Tools**

| Tool | Kind | Ability | Notes |
|---|---|---|---|
| `list_tasks` | read | view | Filter by status/assignee, search, paginate |
| `get_task` | read | view | Fetch one task by uuid |
| `create_task` | write | manage | Creates via the `CreateTask` action |
| `complete_task` | write | manage | Marks done via the `CompleteTask` action |
| `assign_task` | write | manage | Assigns/clears assignee via the `AssignTask` action |

**Resource** — `task_board` (`mcp-kit://tasks/board`): tasks grouped by status, read-only context.

**Prompt** — `triage_runbook`: a parameterised, read-first, human-gated triage runbook.

## Connecting

### STDIO (local — Claude Code runs the server itself)

```bash
claude mcp add mcp-kit -- php artisan mcp:start mcp-kit
```

No authentication layer: implicit OS-user trust. Best for local development.

> Because stdio has no token holder, the gated tools need a user to authorize against. Set
> `MCP_KIT_LOCAL_USER` to the email of the user the local transport should act as (and inherit the
> abilities of). Without it, the tools return *unauthorized* over stdio.
>
> ```dotenv
> MCP_KIT_LOCAL_USER=you@example.com
> ```

### Streamable HTTP (remote — authenticated)

The package registers an authenticated HTTP endpoint at `POST /mcp/tasks` (configurable). It supports
**two auth methods on the same endpoint** — pick whichever your client can use.

#### Method 1 — personal access token (Sanctum)

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

#### Method 2 — OAuth 2.1 (Passport)

For connectors that **cannot** send custom headers (claude.ai). The client discovers the server,
self-registers (Dynamic Client Registration), and runs an authorization-code + PKCE flow.

1. Install Passport, then run the installer's OAuth path — it publishes the consent view and
   generates the Passport keys for you:
   ```bash
   composer require laravel/passport
   php artisan mcp-kit:install --oauth
   ```
2. Turn on the OAuth transport and migrate:
   ```dotenv
   MCP_KIT_WEB_OAUTH_ENABLED=true
   ```
   ```bash
   php artisan migrate
   ```
   With the flag on, the package does the rest **automatically**: it registers `Mcp::oauthRoutes()`,
   switches the endpoint middleware to `auth:sanctum,api`, auto-wires an `api` (Passport) guard (only
   if you haven't defined one), **loads Passport's `oauth_*` migrations** (so a plain `migrate` is
   enough — no `vendor:publish --tag=passport-migrations`), and **wires the consent screen**
   (`mcp-kit::authorize`) — no service-provider edit needed.
3. Allow Claude's redirect domains in the published `config/mcp.php`:
   ```php
   'redirect_domains' => ['https://claude.ai', 'https://claude.com', 'http://localhost'],
   ```
4. Connect — no header needed; Claude drives the OAuth flow:
   ```bash
   claude mcp add --transport http mcp-kit https://your-app.test/mcp/tasks
   ```

> **Customising?** Point `mcp-kit.web.oauth.authorization_view` at your own Blade view to brand the
> consent screen (or set it to `false` to keep Passport's default), and set
> `mcp-kit.web.oauth.load_migrations` to `false` if you'd rather publish and own the `oauth_*`
> migrations yourself. Both are env-overridable.

> **Guard order matters: `sanctum` before `api`.** Passport's token guard strips the `Authorization`
> header when a bearer token fails JWT validation, so a Sanctum token would never reach the sanctum
> guard if Passport ran first. The computed middleware already gets this right.

## Token management UI (optional)

A self-service Livewire + Flux page that manages **both** auth methods (generate/revoke Sanctum
tokens and disconnect OAuth apps) ships as a publishable stub. It requires `livewire/livewire` and
`livewire/flux` in your app:

```bash
php artisan vendor:publish --tag="mcp-kit-ui"
```

This publishes `app/Livewire/McpTokens.php` and `resources/views/livewire/mcp-tokens.blade.php` —
wire up a route and restyle to match your app.

## Configuration

`config/mcp-kit.php` — feature toggle, STDIO handle, HTTP path/throttle/middleware, the OAuth block
(`web.oauth.enabled` + token lifetimes), and ability names. Every value is env-overridable
(`MCP_KIT_*`). Key flags:

| Env | Default | Purpose |
|---|---|---|
| `MCP_KIT_ENABLED` | `true` | Master switch — when off, no routes are registered |
| `MCP_KIT_WEB_OAUTH_ENABLED` | `false` | Adds the OAuth 2.1 transport and the `api` guard |
| `MCP_KIT_WEB_THROTTLE` | `60,1` | Rate limit on the HTTP endpoint |
| `MCP_KIT_LOCAL_USER` | `null` | Email the stdio transport acts as (needed for gated tools over stdio) |

The HTTP middleware is computed automatically: `auth:sanctum` when OAuth is off, `auth:sanctum,api`
when on. Set `web.middleware` to an explicit array to take full control.

## Try it locally (Testbench Workbench)

The package ships a runnable skeleton app under `workbench/` so you can exercise the server — both
transports and **both** auth methods — without a host app:

```bash
composer serve   # one command: recreate sqlite + migrate + seed + Passport keys,
                 # register the demo users in Claude, then boot on 127.0.0.1:8000
```

`composer serve` chains everything for you:

1. `build-db` — recreate the sqlite file, `migrate:fresh` (auto-seeds via `testbench.yaml`), `passport:keys`.
2. `mcp-connect` — issue a fresh token per seeded user and (re)register each in Claude as
   `mcp-kit-manager` / `mcp-kit-viewer`. Non-fatal: if the `claude` CLI is absent it prints the manual
   command and still boots.
3. `serve.sh` — bind a **fixed** host/port (`MCP_KIT_HOST`/`MCP_KIT_PORT`, default `127.0.0.1:8000`).
   It fails rather than drifting to another port, so the registered URL is always correct. Override
   with `MCP_KIT_PORT=9000 composer serve`.

Seeded users: `manager@example.com` (read + write) and `viewer@example.com` (read only). The workbench
sets `MCP_KIT_LOCAL_USER=manager@example.com`, so the stdio tools work too.

Helper scripts:

```bash
composer mcp-token <email>   # issue one token (prints the claude mcp add command)
composer mcp-tokens          # issue tokens for both demo users
composer mcp-connect         # (re)register the demo users in Claude
composer mcp-inspect         # open the MCP Inspector (browser UI) against the stdio server
composer mcp-inspect-web     # open the MCP Inspector against the HTTP endpoint (paste a Bearer token)
```

Test it without a client — speak MCP straight to the stdio transport, or curl the HTTP endpoint:

```bash
# STDIO (acts as MCP_KIT_LOCAL_USER)
printf '%s\n' \
  '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"cli","version":"1"}}}' \
  '{"jsonrpc":"2.0","method":"notifications/initialized"}' \
  '{"jsonrpc":"2.0","id":2,"method":"tools/list"}' \
  | vendor/bin/testbench mcp:start mcp-kit

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

## Testing

```bash
composer test
```

> **Gotcha (testbench):** when testing an MCP server inside a package, you must register
> `Laravel\Mcp\Server\McpServiceProvider` in your `getPackageProviders()`. It registers the
> `resolving(Request::class)` callback that copies tool arguments into the injected `Request`.
> Without it, every tool sees **empty arguments** (validation fails, filters are ignored) even
> though the server otherwise responds. See `tests/TestCase.php`.

## License

MIT. © Nasrul Hazim / CleaniqueCoders.
