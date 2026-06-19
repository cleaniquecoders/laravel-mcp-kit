# Overview

The kit ships a small task-management domain as a working reference, **plus a generic ops toolbox**
(identity, health, logs, jobs, and — when the packages are present — audits, tokens, RBAC, activity).
The value is the patterns — gated tools, Actions, uuid-only output — ready to copy or extend.

## What it exposes

### Task tools (the reference domain)

| Tool | Kind | Ability | Notes |
|---|---|---|---|
| `list_tasks` | read | view | Filter by status/assignee, search, paginate |
| `get_task` | read | view | Fetch one task by uuid |
| `create_task` | write | manage | Creates via the `CreateTask` action |
| `complete_task` | write | manage | Marks done via the `CompleteTask` action |
| `assign_task` | write | manage | Assigns/clears assignee via the `AssignTask` action |

### Generic toolbox

Identity, health, logs, and jobs tools (always on), plus package-gated audit/token/RBAC/activity tools
that auto-register when their backing package is installed. See **[Generic toolbox](03-generic-toolbox.md)**
for the full catalogue and the Tier-3 infrastructure (runtime toggle, health registry, signed-URL
exports, generators, `mcp-kit:doctor`).

### Resource

`task_board` (`mcp-kit://tasks/board`) — tasks grouped by status, as read-only context.

### Prompts

- `triage_runbook` — a parameterised, read-first, human-gated triage runbook for the task domain.
- `support_runbook` — the generic read-first, human-gated investigation flow over the ops tools.

## Where the primitives live

MCP primitives live in `src/`:

| Path | Responsibility |
|---|---|
| `Servers/TaskServer.php` | The registry (`$tools`, `$resources`, `$prompts`) plus `#[Instructions]` |
| `Tools/` | One class per tool — all extend `Tools/McpKitTool.php` (the base) |
| `Resources/` | Read-by-URI context |
| `Prompts/` | Reusable instruction templates |
| `Actions/` | Business rules every write tool funnels through |

## Routes

Routes live in `routes/ai.php`, loaded by `LaravelMcpKitServiceProvider::packageBooted()`:

- **STDIO** via `Mcp::local` — no middleware (implicit OS-user trust).
- **HTTP** via `Mcp::web` — computed auth middleware plus `throttle`.

Both are gated behind `config('mcp-kit.enabled')`.

## Next Steps

- [Conventions](02-conventions.md) — the rules each tool follows.
- [Generic toolbox](03-generic-toolbox.md) — the ops tools and Tier-3 infrastructure.
- [Configuration](../04-configuration/README.md) — toggles and env vars.
