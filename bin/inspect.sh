#!/usr/bin/env bash
#
# Launch the MCP Inspector (browser UI) against the stdio server.
#
# Why not `artisan mcp:inspector`? Under Testbench that command spawns the bare
# skeleton artisan (vendor/orchestra/testbench-core/laravel/artisan), which does
# NOT apply testbench.yaml — so the workbench providers (the demo gates) and env
# (MCP_KIT_LOCAL_USER, the sqlite path) are missing, and every tool comes back
# "unauthorized". Driving the Inspector through the `testbench` wrapper instead
# makes testbench.yaml apply, exactly like `composer serve` / `mcp:start` do.
#
set -euo pipefail

cd "$(dirname "$0")/.."

HANDLE="${MCP_KIT_LOCAL_HANDLE:-mcp-kit}"

exec npx -y @modelcontextprotocol/inspector \
    php vendor/bin/testbench mcp:start "$HANDLE"
