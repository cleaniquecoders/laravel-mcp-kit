# Conventions

The rules every tool follows. MCP is a third UI onto your domain — never a back door — so these
conventions keep it as safe as your web and CLI surfaces.

## The `McpKitTool` base is law

Every tool extends `Tools/McpKitTool.php` and:

1. Declares an `ability()`.
2. Calls `authorizedUser()` first and returns `unauthorized()` on failure.
3. Speaks **uuid only** via `taskSummary()` — the integer `id` is never exposed.

```php
// Gate first, validate, then act.
public function handle(Request $request): Response
{
    $user = $this->authorizedUser($request);

    if ($user === null) {
        return $this->unauthorized();
    }

    // ... validated, then delegated to an Action.
}
```

## Write tools call Actions, not models

`CreateTaskTool` calls `Actions\CreateTask`. The agent, the web UI, and the CLI must share **one** set
of business rules. Any state change goes through an Action class in `src/Actions/`.

## Read vs write is annotated

Read tools carry `#[IsReadOnly]`; write tools deliberately do not — so clients surface them as
state-changing and gate them accordingly.

## uuid-only payloads

Tools emit `uuid`/`code`, never the auto-increment `id`. Use the `*Summary()` helpers to build output.

## Adding a new tool

1. Extend `McpKitTool`, add `#[Name]` / `#[Description]`.
2. Implement `ability()`, `handle()`, `schema()`.
3. Gate first, validate, then act (through an Action for writes).
4. Register it in `TaskServer::$tools`.

## Next Steps

- [Overview](01-overview.md) — the catalogue of tools.
- [Testing](../05-development/02-testing.md) — how to test a tool.
