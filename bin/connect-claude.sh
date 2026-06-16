#!/usr/bin/env bash
#
# Register the workbench MCP server in Claude for every seeded demo user.
# For each user it issues a fresh Sanctum token, removes any existing Claude
# entry of the same name, then adds it back — so re-running is idempotent.
#
# Host/port come from the same env as serve.sh (defaults 127.0.0.1:8000), so
# the registered endpoint always matches where the server binds. Override with:
#   MCP_KIT_PORT=9000 composer serve
#
set -euo pipefail

cd "$(dirname "$0")/.."

HOST="${MCP_KIT_HOST:-127.0.0.1}"
PORT="${MCP_KIT_PORT:-8000}"
URL="${MCP_KIT_URL:-http://${HOST}:${PORT}/mcp/tasks}"

if ! command -v claude >/dev/null 2>&1; then
    echo "claude CLI not found on PATH — skipping Claude registration." >&2
    exit 0
fi

# Run `claude` with all stdio detached from the terminal. The CLI is a Bun
# binary that crashes initialising a TTY writer when invoked from a nested,
# non-interactive context (composer script) — feeding it /dev/null avoids that
# path entirely. Returns claude's real exit code so callers can detect failure.
claude_quiet() {
    claude "$@" </dev/null >/dev/null 2>&1
}

# name:email for each seeded demo user (see workbench DatabaseSeeder).
users=(
    "mcp-kit-manager:manager@example.com"
    "mcp-kit-viewer:viewer@example.com"
)

for entry in "${users[@]}"; do
    name="${entry%%:*}"
    email="${entry##*:}"

    token="$(php vendor/bin/testbench mcp-kit:token "$email" --name="$name" --only-token)"

    claude_quiet mcp remove "$name" || true

    if claude_quiet mcp add --transport http "$name" "$URL" \
        --header "Authorization: Bearer ${token}"; then
        echo "Registered '${name}' (${email}) → ${URL}"
    else
        # Never abort the serve chain just because Claude registration failed.
        echo "⚠ Could not register '${name}' in Claude — add it manually:" >&2
        echo "    claude mcp add --transport http ${name} ${URL} \\" >&2
        echo "      --header \"Authorization: Bearer ${token}\"" >&2
    fi
done
