# Overview

The kit ships a small task-management domain as a working reference. The value is the patterns — gated
tools, Actions, uuid-only output — ready to copy or extend.

## What it exposes

### Tools

| Tool | Kind | Ability | Notes |
|---|---|---|---|
| `list_tasks` | read | view | Filter by status/assignee, search, paginate |
| `get_task` | read | view | Fetch one task by uuid |
| `create_task` | write | manage | Creates via the `CreateTask` action |
| `complete_task` | write | manage | Marks done via the `CompleteTask` action |
| `assign_task` | write | manage | Assigns/clears assignee via the `AssignTask` action |

### Resource

`task_board` (`mcp-kit://tasks/board`) — tasks grouped by status, as read-only context.

### Prompt

`triage_runbook` — a parameterised, read-first, human-gated triage runbook.

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
- [Configuration](../04-configuration/README.md) — toggles and env vars.
