#!/usr/bin/env bash
#
# Boot the workbench MCP server on a fixed host/port. Pinning the port means
# the server either binds exactly here or fails — it never silently moves to
# another port, so the Claude registration (see connect-claude.sh, which reads
# the same env) is always correct.
#
# Override with:  MCP_KIT_PORT=9000 composer serve
#
set -euo pipefail

cd "$(dirname "$0")/.."

HOST="${MCP_KIT_HOST:-127.0.0.1}"
PORT="${MCP_KIT_PORT:-8000}"

exec php vendor/bin/testbench serve --host="$HOST" --port="$PORT"
