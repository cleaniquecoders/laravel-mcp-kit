<?php

namespace CleaniqueCoders\LaravelMcpKit\Servers;

use CleaniqueCoders\LaravelMcpKit\Prompts\TriageRunbookPrompt;
use CleaniqueCoders\LaravelMcpKit\Resources\TaskBoardResource;
use CleaniqueCoders\LaravelMcpKit\Tools\CompleteTaskTool;
use CleaniqueCoders\LaravelMcpKit\Tools\CreateTaskTool;
use CleaniqueCoders\LaravelMcpKit\Tools\GetTaskTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListTasksTool;
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
#[Version('1.0.0')]
#[Instructions(<<<'INSTRUCTIONS'
MCP Kit — a demo task-management server for the Claude Code + MCP training.

Conventions:
- Identify a task by its `uuid` only — never an internal numeric id.
- Every tool is gated by an ability on your token's user. An unauthorized
  tool returns an error, not partial data.
- Task status flows: open → in_progress → done.

Workflow:
- Read first. Use list_tasks / get_task and the task_board resource to
  ground yourself before proposing anything.
- create_task and complete_task CHANGE STATE. Treat them as gated: propose
  the change, then wait for human approval before calling them.
- The triage_runbook prompt encodes the full read-first, human-gated flow.
INSTRUCTIONS)]
class TaskServer extends Server
{
    protected array $tools = [
        ListTasksTool::class,
        GetTaskTool::class,
        CreateTaskTool::class,
        CompleteTaskTool::class,
    ];

    protected array $resources = [
        TaskBoardResource::class,
    ];

    protected array $prompts = [
        TriageRunbookPrompt::class,
    ];
}
