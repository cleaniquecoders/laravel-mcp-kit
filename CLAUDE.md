# CLAUDE.md

Guidance for Claude Code when working in the **Laravel MCP Kit** package. This file doubles as the
worked example for the training's *context engineering* module — it is deliberately short, specific,
and high-signal.

## What this is

A teaching-grade MCP server (`laravel/mcp` ^0.8) exposing a tiny **task** domain. Distilled from a
production gateway-management server. Every pattern is here on purpose; keep it minimal.

**Stack:** PHP 8.4+, Laravel 11/12/13, `laravel/mcp`, `spatie/laravel-package-tools`, Pest 4, larastan, Pint.

## Architecture (the conventions that matter)

- **MCP primitives** live in `src/`:
  - `Servers/TaskServer.php` — the registry (`$tools`, `$resources`, `$prompts`) + `#[Instructions]`.
  - `Tools/` — one class per tool, all extend `Tools/McpKitTool.php` (the base).
  - `Resources/` — read-by-URI context. `Prompts/` — reusable instruction templates.
- **`McpKitTool` base is law.** Every tool: (1) declares an `ability()`, (2) calls `authorizedUser()`
  first and returns `unauthorized()` on failure, (3) speaks **uuid only** via `taskSummary()` —
  never expose the integer `id`.
- **Write tools call Actions, not models.** `CreateTaskTool` → `Actions\CreateTask`. The agent, the
  web UI, and the CLI must share one set of business rules. MCP is a third UI, never a back door.
- **Read vs write is annotated.** Read tools carry `#[IsReadOnly]`; write tools deliberately do not,
  so clients surface them as state-changing and gate them.
- **Routes** in `routes/ai.php`, loaded by `LaravelMcpKitServiceProvider::packageBooted()`. STDIO via
  `Mcp::local`, HTTP via `Mcp::web` (+ `auth:sanctum`, `throttle`). Both behind `config('mcp-kit.enabled')`.

## Rules

1. New tool? Extend `McpKitTool`, add `#[Name]`/`#[Description]`, implement `ability()`, `handle()`,
   `schema()`. Gate first, validate, then act. Register it in `TaskServer::$tools`.
2. Output uuid/code, never `id`. Use the `*Summary()` helpers.
3. Any state change goes through an Action class in `src/Actions/`.
4. Tests use **Pest**, via `TaskServer::actingAs($user)->tool(Foo::class, [...])`. Assert schema +
   auth (`assertHasErrors` for the unauthorized path) + side-effect (DB).
5. `composer test`, `composer format` (Pint), `composer analyse` (larastan level 5) must all pass.

## Gotchas

> **Testbench:** register `Laravel\Mcp\Server\McpServiceProvider` in `getPackageProviders()`, or tool
> arguments arrive **empty** (it wires the `resolving(Request::class)` callback). See `tests/TestCase.php`.

> **Migration is a `.php.stub`** (for publishing). `TestCase::getEnvironmentSetUp()` includes it
> manually so the suite has a `mcp_kit_tasks` table.

> **`env()` only in `config/`.** larastan flags it elsewhere; that's why `config/` is excluded from
> the analysed paths (a package's config dir is not whitelisted by the no-env rule).

> **Gates are the host app's job.** This package never defines `mcp-kit.*` abilities. Tests define
> them with `Gate::define`; a real app uses its own permission system.
