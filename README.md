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

Publish the config and run the migration:

```bash
php artisan vendor:publish --tag="mcp-kit-config"
php artisan vendor:publish --tag="mcp-kit-migrations"
php artisan migrate
```

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
| `mcp-kit.manage-tasks` | `create_task`, `complete_task` |

## What it exposes

**Tools**

| Tool | Kind | Ability | Notes |
|---|---|---|---|
| `list_tasks` | read | view | Filter by status/assignee, search, paginate |
| `get_task` | read | view | Fetch one task by uuid |
| `create_task` | write | manage | Creates via the `CreateTask` action |
| `complete_task` | write | manage | Marks done via the `CompleteTask` action |

**Resource** — `task_board` (`mcp-kit://tasks/board`): tasks grouped by status, read-only context.

**Prompt** — `triage_runbook`: a parameterised, read-first, human-gated triage runbook.

## Connecting

### STDIO (local — Claude Code runs the server itself)

```bash
claude mcp add mcp-kit -- php artisan mcp:start mcp-kit
```

No authentication: implicit OS-user trust. Best for local development.

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

1. Install and set up Passport in your app, then publish its migrations and generate keys:
   ```bash
   composer require laravel/passport
   php artisan vendor:publish --tag=passport-migrations
   php artisan migrate
   php artisan passport:keys
   ```
2. Turn on the OAuth transport:
   ```dotenv
   MCP_KIT_WEB_OAUTH_ENABLED=true
   ```
   The package then registers `Mcp::oauthRoutes()` and switches the endpoint middleware to
   `auth:sanctum,api`. It auto-wires an `api` (Passport) guard only if your `config/auth.php` has not
   already defined one.
3. Allow Claude's redirect domains in the published `config/mcp.php`:
   ```php
   'redirect_domains' => ['https://claude.ai', 'https://claude.com', 'http://localhost'],
   ```
4. (Optional) Publish and wire a consent screen — Passport 13 ships none:
   ```bash
   php artisan vendor:publish --tag="mcp-kit-views"
   ```
   ```php
   \Laravel\Passport\Passport::authorizationView('mcp-kit::authorize');
   ```
5. Connect — no header needed; Claude drives the OAuth flow:
   ```bash
   claude mcp add --transport http mcp-kit https://your-app.test/mcp/tasks
   ```

> **Guard order matters: `sanctum` before `api`.** Passport's token guard strips the `Authorization`
> header when a bearer token fails JWT validation, so a Sanctum token would never reach the sanctum
> guard if Passport ran first. The computed middleware already gets this right.

## Configuration

`config/mcp-kit.php` — feature toggle, STDIO handle, HTTP path/throttle/middleware, the OAuth block
(`web.oauth.enabled` + token lifetimes), and ability names. Every value is env-overridable
(`MCP_KIT_*`). Key flags:

| Env | Default | Purpose |
|---|---|---|
| `MCP_KIT_ENABLED` | `true` | Master switch — when off, no routes are registered |
| `MCP_KIT_WEB_OAUTH_ENABLED` | `false` | Adds the OAuth 2.1 transport and the `api` guard |
| `MCP_KIT_WEB_THROTTLE` | `60,1` | Rate limit on the HTTP endpoint |

The HTTP middleware is computed automatically: `auth:sanctum` when OAuth is off, `auth:sanctum,api`
when on. Set `web.middleware` to an explicit array to take full control.

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
