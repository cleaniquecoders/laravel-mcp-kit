# Generic toolbox

Beyond the task reference domain, the kit ships a **generic, opt-in toolbox** distilled from building
several production MCP servers: the same handful of tools and patterns got rewritten in every one, and
they have **zero domain coupling**, so they belong in the kit.

Two rules hold throughout:

- **Gated and uuid-only** like every other tool — each reads its ability from `config('mcp-kit.abilities.*')`
  so a host remaps it onto its own permission scheme; reads carry `#[IsReadOnly]`, writes funnel through
  an Action.
- **Opt-in by presence** — a Tier-2 tool auto-registers *only* when its backing package (and table) exist.
  Absent the package, the tool stays out of the registry and the server degrades gracefully. The logic
  lives in one place: [`Servers/ToolRegistry.php`](../../src/Servers/ToolRegistry.php), read by
  `TaskServer::boot()`.

## Tier 1 — pure-generic core (always on)

| Tool | Kind | Ability | Notes |
|---|---|---|---|
| `whoami` | read | *(any authenticated user)* | Identity (uuid/name/email) + the abilities the token holder has. Call it first. |
| `list_my_abilities` | read | *(any authenticated user)* | Every MCP Kit ability with a granted/denied flag — the full gate. |
| `system_health` | read | `view-system` | DB / cache / queue / storage reachability + app-defined checks (see below). |
| `tail_logs` | read | `view-logs` | Recent entries from a log file; whole entries (stack traces stay attached). |
| `search_logs` | read | `view-logs` | Search entries by text and/or level across files. |
| `export_logs` | read | `export-logs` | A filtered slice as a short-lived **signed download URL**, never inlined. |
| `list_failed_jobs` | read | `view-jobs` | The `failed_jobs` store, paginated; each row carries the failer `id` (the retry handle) and the job's logical `uuid`. |
| `retry_failed_job` | **write** | `manage-jobs` | Re-dispatch via the `RetryFailedJob` action. |
| `queue_status` | read | `view-jobs` | Pending size + failed count; notes Horizon when present. |
| `scheduled_tasks` | read | `view-system` | Scheduler entries with expression and next run. |

## Tier 2 — auto-registered when the package is present

| Tool | Kind | Ability | Requires |
|---|---|---|---|
| `list_audits` | read | `view-audits` | `owen-it/laravel-auditing` |
| `issue_mcp_token` | **write** | `manage-tokens` | `laravel/sanctum` |
| `list_mcp_tokens` | read | `manage-tokens` | `laravel/sanctum` |
| `revoke_mcp_token` | **write** | `manage-tokens` | `laravel/sanctum` |
| `list_roles` | read | `view-permissions` | `spatie/laravel-permission` |
| `list_permissions` | read | `view-permissions` | `spatie/laravel-permission` |
| `get_user_permissions` | read | `view-permissions` | `spatie/laravel-permission` |
| `list_activities` | read | `view-activities` | `spatie/laravel-activitylog` |

The token tools only ever touch the **authenticated user's own** tokens, and only those whose name
starts with the configured prefix (`mcp-kit.tokens.prefix`) — so an agent manages its own MCP
connections without seeing or revoking a user's other application tokens.

## Tier 3 — infrastructure & patterns

- **Runtime toggle** — [`Support/McpToggle.php`](../../src/Support/McpToggle.php) is a cache-backed
  on/off switch `routes/ai.php` reads when it registers the server. `MCP_KIT_ENABLED` stays the
  deploy-time master kill-switch; the toggle layers under it and clears the route cache on change.
  Flip it with `php artisan mcp-kit:toggle on|off|status`, the `manage-mcp` ability, or the publishable
  Livewire settings card (`--ui`).
- **Health registry** — register app checks the `system_health` tool reports:
  ```php
  // In a service provider's boot():
  use Laravel\Mcp\Facades\Mcp;

  Mcp::healthCheck('keycloak', fn () => Http::timeout(2)->get(config('keycloak.url'))->successful());
  ```
  A check returns a bool or an array with a `healthy` key; thrown exceptions are caught and reported
  unhealthy, so one broken dependency never takes the tool down.
- **Signed-URL export helper** — [`Actions/ExportToSignedUrl.php`](../../src/Actions/ExportToSignedUrl.php)
  writes export contents to a disk and returns a temporary signed URL served by the `mcp-kit.download`
  route. The base tool exposes it as `$this->download($contents, $filename)`. The signature *is* the
  capability; the `signed` middleware rejects tampered or expired links.
- **Generators** — scaffold the gate-first pattern (not the bare `laravel/mcp` primitives):
  `mcp-kit:make-tool`, `mcp-kit:make-resource`, `mcp-kit:make-prompt`.
- **`mcp-kit:doctor`** — verify transports, auth, OAuth keys, tables, and which Tier-2 tools registered.
  Its checks live in [`Support/McpConfigSnapshot.php`](../../src/Support/McpConfigSnapshot.php) so the
  command and the settings UI report identical results.
- **Settings & operations UI** — a publishable, `manage-mcp`-gated Livewire page
  ([`stubs/Livewire/McpSettings.php.stub`](../../stubs/Livewire/McpSettings.php.stub), Flux) that flips
  the runtime toggle and shows health, the effective config, doctor results, and the live tool registry.
  Publish with `mcp-kit:install --ui`; preview build-free in the workbench (`composer serve` → `/mcp`).
  It reads the same services as everything else — `McpConfigSnapshot`,
  [`Support/SystemHealth.php`](../../src/Support/SystemHealth.php), `McpToggle`, `ToolRegistry` — with no
  business logic of its own.
- **Base helpers** — `download()` (signed URL), `paginatedSummary()`, `configuredAbility()`,
  `requiresAbility()`, and `unauthorized()` on [`Tools/McpKitTool.php`](../../src/Tools/McpKitTool.php).
- **`support_runbook` prompt** — the generic read-first, human-gated investigation flow (orient →
  observe → diagnose → propose → stop at the human gate), distinct from the task-specific
  `triage_runbook`.

## Stays app-specific (NOT in the kit)

Anything domain-coupled — identity sync, gateway provisioning, directory-presence checks — funnels
through project-specific Actions and stays in the host. The kit gives you the generic spine; the
domain is yours.

## Next Steps

- [Conventions](02-conventions.md) — the rules each tool follows.
- [Configuration](../04-configuration/01-reference.md) — the abilities, toggle, and ops config.
