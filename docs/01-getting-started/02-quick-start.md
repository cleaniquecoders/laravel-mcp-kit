# Quick Start

Seed some demo data, connect a client, and watch the tools respond.

## Seed demo tasks

Give the read tools something to return:

```bash
php artisan mcp-kit:demo          # seed demo tasks
php artisan mcp-kit:demo --fresh  # wipe first, then seed
```

## Connect a client

The fastest path is the local STDIO transport — Claude Code runs the server itself:

```bash
claude mcp add mcp-kit -- php artisan mcp:start mcp-kit
```

Because STDIO has no token holder, set the user the local transport acts as:

```dotenv
MCP_KIT_LOCAL_USER=you@example.com
```

See [Authentication](../03-authentication/README.md) for the HTTP transport (Sanctum tokens and the
OAuth 2.1 flow).

## Verify without a client

Speak MCP straight to the STDIO transport:

```bash
printf '%s\n' \
  '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"cli","version":"1"}}}' \
  '{"jsonrpc":"2.0","method":"notifications/initialized"}' \
  '{"jsonrpc":"2.0","id":2,"method":"tools/list"}' \
  | php artisan mcp:start mcp-kit
```

## Next Steps

- [Architecture](../02-architecture/README.md) — what each tool does.
- [Try it locally](../05-development/01-workbench.md) — run the full server with the Workbench.
