# Changelog

All notable changes to `cleaniquecoders/laravel-mcp-kit` will be documented in this file.

## 1.1.0 - 2026-06-19

The **generic toolbox** line on top of the task-demo foundation
([#14](https://github.com/cleaniquecoders/laravel-mcp-kit/issues/14)). Every new tool is gated on a
configurable ability, speaks uuid-only, annotates reads with `#[IsReadOnly]`, and auto-registers only
when its backing package (and table) are present — the kit degrades gracefully and never forces a
dependency.

### Tier 1 — pure-generic core tools (always on)

- `whoami` / `list_my_abilities` — identity + the abilities the token holder has (self-discovery).
- `system_health` — DB/cache/queue/storage reachability + app-defined checks.
- `tail_logs` / `search_logs` / `export_logs` — read `storage/logs`; export returns a signed URL.
- `list_failed_jobs` / `retry_failed_job` (via the `RetryFailedJob` action) / `queue_status`.
- `scheduled_tasks` — scheduler entries with expression and next run.

### Tier 2 — auto-registered when the package is present

- `list_audits` (`owen-it/laravel-auditing`).
- `issue_mcp_token` / `list_mcp_tokens` / `revoke_mcp_token` (`laravel/sanctum`), scoped to the
  authenticated user's own MCP-prefixed tokens.
- `list_roles` / `list_permissions` / `get_user_permissions` (`spatie/laravel-permission`).
- `list_activities` (`spatie/laravel-activitylog`).

### Tier 3 — infrastructure & patterns

- **Runtime toggle** — `Support\McpToggle` + `mcp-kit:toggle` command + publishable Livewire card.
  Cache-backed, route-cache-aware, layered under the `MCP_KIT_ENABLED` master switch.
- **Health registry** — `Mcp::healthCheck('name', fn () => …)` checks surfaced by `system_health`.
- **Signed-URL export helper** — `Actions\ExportToSignedUrl` + the `mcp-kit.download` signed route,
  exposed on the base tool as `download()`.
- **Generators** — `mcp-kit:make-tool` / `make-resource` / `make-prompt` scaffold the gate-first pattern.
- **`mcp-kit:doctor`** — verify token/transport/OAuth wiring and which Tier-2 tools registered.
- **Base helpers** — `download()`, `paginatedSummary()`, `configuredAbility()`, `requiresAbility()`.
- **`support_runbook` prompt** — the generic read-first, human-gated investigation flow.

The `McpKitTool` base is now fully generic (task helpers moved to a `Concerns\InteractsWithTasks` trait),
and `TaskServer` builds its registry in `boot()` via `Servers\ToolRegistry`.

## 1.0.3 - 2026-06-18

- Serve `/.well-known/openid-configuration` when OAuth is enabled, aliasing it (308) to the
  authorization-server metadata. `laravel/mcp`'s `oauthRoutes()` does not register OpenID discovery,
  but some connectors (and laravel/mcp's own client) probe it — so hosts no longer need a reverse-proxy
  redirect. Toggle with `MCP_KIT_OAUTH_OPENID_CONFIG`.

## 1.0.2 - 2026-06-18

- Add a vendor-neutral MCP OAuth production deployment guide (`docs/06-deployment`): Passport keys per
  environment and their failure signature, CDN/WAF allow-list for Claude's bots, and reverse-proxy
  rules for `/.well-known/*` with a reference nginx recipe.
- Surface the top production gotchas in the `mcp-kit:install --oauth` post-install output.

## 1.0.1 - 2026-06-18

- Restructure documentation into a numbered `docs/` tree (getting-started, architecture,
  authentication, configuration, development), each with a context README/TOC.
- Minimise the root README to an overview + features + quick install/start + documentation links,
  with standard `flat-square` badges.

## 1.0.0 - 2026-06-17

Initial release.

- Task domain: `Task` model (dual-key uuid/id), `TaskStatus` enum, migration, factory.
- MCP server `TaskServer` with `#[Instructions]`.
- Tools: `list_tasks`, `get_task` (read) and `create_task`, `complete_task`, `assign_task` (write,
  via Actions).
- `task_board` resource and `triage_runbook` prompt.
- Per-tool Gate authorization through the `McpKitTool` base; uuid-only payloads.
- STDIO + authenticated HTTP transports via `routes/ai.php`.
- Two HTTP auth methods: Sanctum personal access tokens and OAuth 2.1 (Passport).
- One-step setup: `mcp-kit:install` publishes config + migration; `--oauth` also publishes the
  consent view and generates Passport keys, `--ui` publishes the token-management UI.
- One-flag OAuth: with `MCP_KIT_WEB_OAUTH_ENABLED=true` the package auto-loads Passport's `oauth_*`
  migrations and auto-wires the consent screen (`mcp-kit::authorize`) — no service-provider edits or
  extra publish steps. Overridable via `mcp-kit.web.oauth.authorization_view` / `load_migrations`.
- `mcp-kit:demo` seed command and `mcp-kit:token` token-issuing command.
- Pest suite covering schema, authorization, side-effects, dual auth, and the full OAuth
  authorization-code + PKCE flow end to end (consent → token → authenticated MCP call).
