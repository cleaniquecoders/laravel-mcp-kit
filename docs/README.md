# Documentation

Full documentation for **Laravel MCP Kit** — a reusable starter package for adding a Model Context
Protocol (MCP) server to your Laravel projects, built on [`laravel/mcp`](https://github.com/laravel/mcp).

The root [README](../README.md) is the quick overview; everything detailed lives here.

## Documentation Structure

### [01. Getting Started](01-getting-started/README.md)

Install the package, define the required gates, and make your first MCP call.

### [02. Architecture](02-architecture/README.md)

What the server exposes (tools, resources, prompts) and the conventions every tool follows.

### [03. Authentication](03-authentication/README.md)

Connecting clients over STDIO and HTTP, with Sanctum tokens or the OAuth 2.1 (Passport) flow.

### [04. Configuration](04-configuration/README.md)

The `config/mcp-kit.php` reference and every `MCP_KIT_*` environment variable.

### [05. Development](05-development/README.md)

Run the server end to end with the Testbench Workbench, and the testing conventions.

### [06. Deployment](06-deployment/README.md)

Running the OAuth transport in production: Passport keys, CDN/WAF, and reverse-proxy rules.

## Quick Start

New to the package? Start with [Installation](01-getting-started/01-installation.md), then
[Quick Start](01-getting-started/02-quick-start.md).

## Finding Information

- **Concepts** (what the server exposes, the rules) — see [Architecture](02-architecture/README.md).
- **How-to** (connect a client, enable OAuth) — see [Authentication](03-authentication/README.md).
- **Reference** (config + env vars) — see [Configuration](04-configuration/README.md).
- **Production** (deploy the OAuth transport) — see [Deployment](06-deployment/README.md).
