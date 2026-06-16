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

# The Inspector binds a UI port (CLIENT_PORT, default 6274) and a proxy port
# (SERVER_PORT, default 6277), and doesn't always release them on Ctrl-C —
# leaving "PORT IS IN USE". If they're occupied, clear any stale Inspector
# processes (scoped to the inspector only) before launching.
CLIENT_PORT="${CLIENT_PORT:-6274}"
SERVER_PORT="${SERVER_PORT:-6277}"
export CLIENT_PORT SERVER_PORT

if lsof -iTCP:"$CLIENT_PORT" -iTCP:"$SERVER_PORT" -sTCP:LISTEN >/dev/null 2>&1; then
    echo "Inspector port busy — clearing stale @modelcontextprotocol/inspector processes…"
    pkill -f '@modelcontextprotocol/inspector' 2>/dev/null || true
    # Give the OS a moment to release the sockets.
    for _ in 1 2 3 4 5; do
        lsof -iTCP:"$CLIENT_PORT" -iTCP:"$SERVER_PORT" -sTCP:LISTEN >/dev/null 2>&1 || break
        sleep 1
    done
fi

exec npx -y @modelcontextprotocol/inspector \
    php vendor/bin/testbench mcp:start "$HANDLE"
