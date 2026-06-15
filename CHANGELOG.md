# Changelog

All notable changes to `cleaniquecoders/laravel-mcp-kit` will be documented in this file.

## 1.0.0 - Unreleased

Initial release — the training/reference MCP server.

- Task domain: `Task` model (dual-key uuid/id), `TaskStatus` enum, migration, factory.
- MCP server `TaskServer` with `#[Instructions]`.
- Tools: `list_tasks`, `get_task` (read) and `create_task`, `complete_task` (write, via Actions).
- `task_board` resource and `triage_runbook` prompt.
- Per-tool Gate authorization through the `McpKitTool` base; uuid-only payloads.
- STDIO + authenticated HTTP transports via `routes/ai.php`.
- `mcp-kit:demo` seed command.
- Pest suite (13 tests) covering schema, authorization, and side-effects.
