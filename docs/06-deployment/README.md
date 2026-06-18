# Deployment

Running the MCP server in production — specifically the OAuth 2.1 transport, where the hard parts are
infrastructure, not application code.

## Table of Contents

### [1. MCP OAuth in Production](01-mcp-oauth-production.md)

The operational checklist for the header-less OAuth flow: Passport keys per environment, and the CDN /
reverse-proxy rules that let a cloud connector (claude.ai) reach discovery, registration, and token
endpoints.

## Related Documentation

- [OAuth 2.1](../03-authentication/02-oauth.md) — the application-side setup.
- [Configuration](../04-configuration/README.md) — the env vars referenced here.
