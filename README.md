# Laravel MCP Kit

[![Tests](https://img.shields.io/badge/tests-13%20passing-brightgreen)](.)
[![Laravel MCP](https://img.shields.io/badge/laravel%2Fmcp-%5E0.8-orange)](https://github.com/laravel/mcp)

A small, **teaching-grade** Model Context Protocol (MCP) server for Laravel, built on the
official [`laravel/mcp`](https://github.com/laravel/mcp) package. It exposes a tiny task-management
domain to AI agents (Claude Code, Claude Desktop, claude.ai) and demonstrates — in the least code
possible — the patterns you need for a **production** MCP server:

- **Tools** (read + write), **Resources** (read-by-URI context), and **Prompts** (reusable templates)
- **Per-tool authorization** via Gate abilities — MCP is a third UI, never a back door
- **uuid-only** inputs/outputs — the internal auto-increment id never leaks to the agent
- **Write tools funnel through Action classes** — the agent, the web UI and the CLI share one set of business rules
- **STDIO** (local) and **Streamable HTTP** (remote, authenticated) transports
- **Honest annotations** (`#[IsReadOnly]`) so clients know which tools change state and gate them

It is the companion package to the *Claude Code + MCP with Laravel* 2-day training. It is intentionally
distilled from a production gateway-management server, so every pattern here scales up.

> This is a **private** training/reference package. Not published to Packagist.

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

The package registers an authenticated HTTP endpoint at `POST /mcp/tasks` (configurable). Issue a
Sanctum token for a user who holds the abilities, then:

```bash
claude mcp add --transport http mcp-kit https://your-app.test/mcp/tasks \
  --header "Authorization: Bearer <token>"
```

To support external clients that cannot send custom headers (claude.ai connectors), add Passport
OAuth 2.1 and switch the middleware to `auth:sanctum,api` (see the training Day-2 auth lab). **Order
matters: `sanctum` before `api`.**

## Configuration

`config/mcp-kit.php` — feature toggle, STDIO handle, HTTP path + middleware, ability names. Every
value is env-overridable (`MCP_KIT_*`).

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
