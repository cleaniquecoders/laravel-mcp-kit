# CLAUDE.md

Guidance for Claude Code when working in the **Laravel MCP Kit** package.

## What this is

A **reusable starter package** you drop into your own Laravel projects to bootstrap an MCP server
fast — distilled from production patterns. It ships a small **task** domain as a working reference;
the value is the patterns (gated tools, Actions, uuid-only output, token + OAuth auth) ready to copy
or extend. Keep it production-quality and high-signal, not minimal-for-its-own-sake.

**Stack:** PHP 8.4+, Laravel 11/12/13, `laravel/mcp`, `spatie/laravel-package-tools`, Pest 4, larastan, Pint.

## Commands

- `composer test` — run the Pest suite. Single test: `vendor/bin/pest --filter='create_task'`;
  one file: `vendor/bin/pest tests/Feature/WriteToolsTest.php`.
- `composer test-coverage` — suite with coverage.
- `composer format` — Pint (run before committing).
- `composer analyse` — larastan, level 5 (`config/` excluded; see Gotchas).
- `php artisan mcp-kit:install [--oauth] [--ui] [--force]` — one-shot host setup: publishes config +
  migration; `--oauth` also publishes the consent view and runs `passport:keys`; `--ui` publishes the
  Livewire UI. Idempotent. See `Commands/InstallCommand.php`.
- `php artisan mcp-kit:demo [--fresh]` — seed demo tasks so the read tools have something to return
  (host app only; `--fresh` wipes first). See `Commands/SeedDemoTasksCommand.php`.
- `php artisan mcp-kit:token {email} [--name=] [--only-token]` — issue a Sanctum token for the HTTP
  endpoint and print the `claude mcp add` command. `--only-token` prints just the raw token (for
  scripting). See `Commands/IssueTokenCommand.php`.
- `php artisan mcp-kit:doctor` — verify transports/auth/OAuth-keys/tables wiring and report which
  Tier-2 tools auto-registered. See `Commands/DoctorCommand.php`.
- `php artisan mcp-kit:toggle {on|off|status}` — flip the cache-backed runtime switch (under the
  `MCP_KIT_ENABLED` master). See `Commands/ToggleCommand.php` + `Support/McpToggle.php`.
- `php artisan mcp-kit:make-tool|make-resource|make-prompt {Name}` — scaffold the gate-first pattern
  (extends `McpKitTool` / auth-gated resource / read-first runbook). Stubs in `stubs/mcp-kit-*.stub`.

**Workbench helper scripts** (composer, Testbench-only — see `bin/` and `composer.json`):
- `composer serve` — `build-db` → `mcp-connect` → `bin/serve.sh`. One command to prepare a clean DB,
  register the demo users in Claude, and boot on a fixed host/port.
- `composer mcp-connect` (`bin/connect-claude.sh`) — issue a fresh token per seeded user and
  (re)register each in Claude (`mcp-kit-manager`, `mcp-kit-viewer`). Runs `claude` with stdio detached
  (Bun TTY-crash workaround) and is **non-fatal** so a `claude` failure never aborts serve.
- `composer mcp-tokens` — issue tokens for both demo users without registering.
- `composer mcp-inspect` / `mcp-inspect-web` — open the MCP Inspector (browser UI) against the
  stdio / HTTP transport.
- Host/port is a single source of truth: `MCP_KIT_HOST`/`MCP_KIT_PORT` (default `127.0.0.1:8000`),
  read by both `bin/serve.sh` and `bin/connect-claude.sh`. The port is "sticky" (registration uses it):
  if it's busy, `bin/serve.sh` reclaims a *stale workbench server* it finds there (the usual
  "Address already in use" cause), and only if an unrelated process owns it falls back to the next free
  port and warns to re-run `mcp-connect`. It never kills processes it didn't start.

## Architecture (the conventions that matter)

- **MCP primitives** live in `src/`:
  - `Servers/TaskServer.php` — builds its registry in `boot()` from `Servers/ToolRegistry.php` + `#[Instructions]`.
  - `Servers/ToolRegistry.php` — the single source of truth for what's exposed. Tier-1 tools are always
    on; Tier-2 tools auto-register only when their package/table exist (`Tool::isAvailable()`).
  - `Tools/` — one class per tool, all extend `Tools/McpKitTool.php` (the generic base). Task-domain
    helpers live in `Tools/Concerns/InteractsWithTasks.php`, not the base.
  - `Resources/` — read-by-URI context. `Prompts/` — reusable instruction templates.
  - `Support/` — `HealthRegistry` (backs `Mcp::healthCheck()` + `system_health`), `McpToggle` (runtime
    switch). `Http/Controllers/DownloadExportController.php` serves the signed `mcp-kit.download` route.
- **Generic toolbox** (issue #14): identity (`whoami`/`list_my_abilities`), `system_health`, logs
  (`tail`/`search`/`export`), jobs (`list_failed_jobs`/`retry_failed_job`/`queue_status`/`scheduled_tasks`),
  and package-gated audits/tokens/RBAC/activity. See `docs/02-architecture/03-generic-toolbox.md`.
- **`McpKitTool` base is law.** Every tool: (1) declares an `ability()`, (2) calls `authorizedUser()`
  first and returns `unauthorized()` on failure, (3) speaks **uuid only** via `taskSummary()` —
  never expose the integer `id`.
- **Write tools call Actions, not models.** `CreateTaskTool` → `Actions\CreateTask`. The agent, the
  web UI, and the CLI must share one set of business rules. MCP is a third UI, never a back door.
- **Read vs write is annotated.** Read tools carry `#[IsReadOnly]`; write tools deliberately do not,
  so clients surface them as state-changing and gate them.
- **Routes** in `routes/ai.php`, loaded by `LaravelMcpKitServiceProvider::packageBooted()`. STDIO via
  `Mcp::local`, HTTP via `Mcp::web` (+ computed auth + `throttle`). Both behind `config('mcp-kit.enabled')`.

## Auth (two methods, one endpoint)

- The HTTP endpoint accepts **either** a Sanctum personal access token (header clients: Claude
  Code/Desktop) **or** an OAuth 2.1 Passport token (header-less connectors: claude.ai).
- OAuth is **off by default**. `MCP_KIT_WEB_OAUTH_ENABLED=true` makes `routes/ai.php` register
  `Mcp::oauthRoutes()` and switches the computed middleware from `auth:sanctum` to `auth:sanctum,api`.
- `configureOAuth()` in the provider wires Passport **non-destructively** when OAuth is on: adds the
  `api` guard only if the host has not defined one, sets token TTLs, **wires the consent view**
  (`mcp-kit::authorize`, opt-out via `web.oauth.authorization_view`), and **loads Passport's `oauth_*`
  migrations** (opt-out via `web.oauth.load_migrations`) so a plain `migrate` suffices. All gated on
  `class_exists(Passport)`. Net effect: flipping `MCP_KIT_WEB_OAUTH_ENABLED=true` is the only code
  change a host needs — no service-provider edits.
- Per-tool authorization is unchanged either way — the guard authenticates; `ability()` authorizes.
- OAuth flow tests boot with OAuth on via `tests/OAuthTestCase.php` (separate `tests/OAuth/` tree, so
  Pest's per-directory base class doesn't clash with `tests/Feature/`).

## Rules

1. New tool? Extend `McpKitTool`, add `#[Name]`/`#[Description]`, implement `ability()`, `handle()`,
   `schema()`. Gate first, validate, then act. Register it in `TaskServer::$tools`.
2. Output uuid/code, never `id`. Use the `*Summary()` helpers.
3. Any state change goes through an Action class in `src/Actions/`.
4. Tests use **Pest**, via `TaskServer::actingAs($user)->tool(Foo::class, [...])`. Assert schema +
   auth (`assertHasErrors` for the unauthorized path) + side-effect (DB). Grab actors from the
   `viewer()` / `manager()` / `nobody()` helpers in `tests/Pest.php` to cover the gated paths.
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

> **Stdio has no token holder.** `Mcp::local()` takes no middleware, so `$request->user()` is null over
> stdio and every gated tool would return *unauthorized*. `McpKitTool::authorizedUser()` falls back to
> `localUser()` — the `mcp-kit.local.user` email — **only** when `App::runningInConsole()` and no
> request user exists, so an HTTP request that failed auth can never reach it. Null config = stdio stays
> gated. The workbench sets `MCP_KIT_LOCAL_USER=manager@example.com`.

> **`mcp:inspector` (stdio) is incompatible with the workbench.** It spawns `base_path('artisan')`
> (`vendor/orchestra/testbench-core/laravel/artisan`), which never applies `testbench.yaml` — so the
> workbench providers (gates), DB path, and env (`MCP_KIT_LOCAL_USER`) are all absent and every tool
> returns *unauthorized*. `composer mcp-inspect` therefore runs `bin/inspect.sh`, which drives the
> Inspector through the `testbench` wrapper (`npx @modelcontextprotocol/inspector php vendor/bin/testbench
> mcp:start mcp-kit`) so `testbench.yaml` applies. The HTTP variant (`mcp-inspect-web`) is fine — it only
> points the Inspector at `url(route)`, it doesn't spawn artisan.

> **Guard order: `sanctum` before `api`.** Passport's `TokenGuard` strips the `Authorization` header
> when a bearer token fails JWT validation, so a Sanctum token never reaches the sanctum guard if
> Passport runs first. The computed middleware (`auth:sanctum,api`) already orders it correctly.

> **One token trait per model.** `Sanctum\HasApiTokens` and `Passport\HasApiTokens` cannot coexist on
> the same model (incompatible `$accessToken` property types). Use **only** the Sanctum trait;
> Passport's guard calls `withAccessToken()` itself.

> **Passport 13 ships no migrations or consent view — the kit fills both gaps.** Rather than make
> hosts `vendor:publish --tag=passport-migrations` + wire `Passport::authorizationView(...)` by hand,
> `configureOAuth()` loads Passport's migrations and binds the `mcp-kit::authorize` view when OAuth is
> on. So a host only needs Passport installed, `MCP_KIT_WEB_OAUTH_ENABLED=true`, `migrate`, and
> `passport:keys` (the last two run by `mcp-kit:install --oauth`). Both auto-wirings are opt-out via
> `web.oauth.load_migrations` / `web.oauth.authorization_view` for hosts that own those themselves.
