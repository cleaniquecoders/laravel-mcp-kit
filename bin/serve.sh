#!/usr/bin/env bash
#
# Boot the workbench MCP server. The port is "sticky": connect-claude.sh
# registers the Claude URL with this exact host/port, so we try hard to keep it.
#
# If the port is busy:
#   1. STALE workbench server (a leftover `testbench serve` from a previous run)
#      → reclaim it: kill it and bind the same port, so the registered URL stays
#      correct. This is the common "Address already in use" cause.
#   2. Unrelated process owns the port → fall back to the next free port and
#      warn (re-run `composer mcp-connect` so the registration matches). We do
#      NOT kill processes we did not start.
#
# Override with:  MCP_KIT_PORT=9000 composer serve
#
set -euo pipefail

cd "$(dirname "$0")/.."

HOST="${MCP_KIT_HOST:-127.0.0.1}"
PORT="${MCP_KIT_PORT:-8000}"

# PIDs LISTENing on a TCP port (empty if free). lsof works on macOS + Linux.
pids_on_port() { lsof -nP -tiTCP:"$1" -sTCP:LISTEN 2>/dev/null || true; }

# Kill only OUR leftover workbench dev server (testbench's built-in server),
# never an unrelated process that happens to share the port.
reclaim_stale_workbench() {
    local port="$1" pid cmd
    for pid in $(pids_on_port "$port"); do
        cmd="$(ps -p "$pid" -o command= 2>/dev/null || true)"
        if printf '%s' "$cmd" | grep -q 'testbench-core/laravel/server\.php'; then
            echo "Port $port held by a stale workbench server (pid $pid) — reclaiming it."
            kill "$pid" 2>/dev/null || true
        fi
    done
}

if [ -n "$(pids_on_port "$PORT")" ]; then
    reclaim_stale_workbench "$PORT"
    sleep 1
fi

# Still busy (an unrelated process owns it)? Pick the next free port and warn.
if [ -n "$(pids_on_port "$PORT")" ]; then
    echo "Port $PORT is in use by another process; searching for a free port..."
    for candidate in $(seq "$PORT" "$((PORT + 20))"); do
        if [ -z "$(pids_on_port "$candidate")" ]; then
            PORT="$candidate"
            break
        fi
    done
    echo "-> Using http://$HOST:$PORT instead."
    echo "   NOTE: the Claude registration still points at the old port. Re-run"
    echo "         'MCP_KIT_PORT=$PORT composer mcp-connect' (or update the URL)."
fi

echo "Serving MCP Kit workbench on http://$HOST:$PORT"
exec php vendor/bin/testbench serve --host="$HOST" --port="$PORT"
