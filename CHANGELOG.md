# Changelog

All notable changes to `cleaniquecoders/laravel-mcp-kit` will be documented in this file.

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
