<?php

namespace CleaniqueCoders\LaravelMcpKit\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The MCP server: a registry of primitives (tools, resources, prompts)
 * plus a set of #[Instructions] that orient the agent.
 *
 * Good instructions are worth as much as good tools — they tell the
 * agent the conventions (uuid not id), the workflow (read-first), and the
 * safety rules (writes wait for the gate) so it behaves well by default.
 */
#[Name('mcp-kit')]
#[Version('1.1.0')]
#[Instructions(<<<'INSTRUCTIONS'
MCP Kit — a Laravel MCP server: a reference task domain plus a generic ops
toolbox (identity, health, logs, jobs, and — when the packages are installed —
audits, tokens, RBAC and activity).

Conventions:
- Identify records by their public `uuid` / `id`, never an internal numeric id
  the kit hides.
- Every tool is gated by an ability on your token's user. An unauthorized tool
  returns an error, not partial data.
- Task status flows: open → in_progress → done.

Workflow:
- Orient first. Call `whoami` and `list_my_abilities` to learn who you are and
  what you may do, then read with list_tasks / get_task / system_health /
  tail_logs before proposing anything.
- Tools WITHOUT a read-only annotation (create_task, complete_task,
  retry_failed_job, issue/revoke_mcp_token) CHANGE STATE. Propose the change,
  then wait for human approval before calling them.
- The triage_runbook and support_runbook prompts encode the full read-first,
  human-gated flow.
INSTRUCTIONS)]
class TaskServer extends Server
{
    /**
     * Build the registry in boot() (not as static arrays) so Tier-2 tools can
     * auto-register based on which packages the host has installed. See
     * {@see ToolRegistry}.
     */
    protected function boot(): void
    {
        $this->tools = ToolRegistry::tools();
        $this->resources = ToolRegistry::resources();
        $this->prompts = ToolRegistry::prompts();
    }
}
