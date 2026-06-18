# Architecture

What the MCP server exposes and the conventions that keep it production-quality.

## Table of Contents

### [1. Overview](01-overview.md)

The tools, resource, and prompt the server ships, and where the MCP primitives live in `src/`.

### [2. Conventions](02-conventions.md)

The rules every tool follows: the `McpKitTool` base, uuid-only output, Actions for writes, and
read-vs-write annotations.

## Related Documentation

- [Getting Started](../01-getting-started/README.md) — install and wire the gates.
- [Authentication](../03-authentication/README.md) — how clients authorize against these tools.
