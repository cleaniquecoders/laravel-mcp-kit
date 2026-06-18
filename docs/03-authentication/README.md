# Authentication

How clients connect to the server, and the two HTTP auth methods supported on one endpoint.

## Table of Contents

### [1. Connecting](01-connecting.md)

The STDIO (local) and Streamable HTTP (remote) transports, and Sanctum personal access tokens.

### [2. OAuth 2.1](02-oauth.md)

The header-less OAuth 2.1 (Passport) flow for connectors like claude.ai — one flag to enable.

## Auth at a glance

| Transport | Auth | Best for |
|---|---|---|
| STDIO (`mcp:start`) | Implicit OS-user trust (`MCP_KIT_LOCAL_USER`) | Local development |
| HTTP — Sanctum token | `Authorization: Bearer <token>` header | Claude Code / Desktop |
| HTTP — OAuth 2.1 | Authorization-code + PKCE, no header | claude.ai connectors |

Per-tool authorization is unchanged either way: the guard **authenticates**; the tool's `ability()`
**authorizes**.

## Related Documentation

- [Configuration](../04-configuration/README.md) — the middleware and OAuth config.
- [Architecture](../02-architecture/README.md) — what the authorized client can call.
