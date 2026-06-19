<?php

namespace CleaniqueCoders\LaravelMcpKit\Servers;

use CleaniqueCoders\LaravelMcpKit\Prompts\SupportRunbookPrompt;
use CleaniqueCoders\LaravelMcpKit\Prompts\TriageRunbookPrompt;
use CleaniqueCoders\LaravelMcpKit\Resources\TaskBoardResource;
use CleaniqueCoders\LaravelMcpKit\Tools\AssignTaskTool;
use CleaniqueCoders\LaravelMcpKit\Tools\CompleteTaskTool;
use CleaniqueCoders\LaravelMcpKit\Tools\CreateTaskTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ExportLogsTool;
use CleaniqueCoders\LaravelMcpKit\Tools\GetTaskTool;
use CleaniqueCoders\LaravelMcpKit\Tools\GetUserPermissionsTool;
use CleaniqueCoders\LaravelMcpKit\Tools\IssueMcpTokenTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListActivitiesTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListAuditsTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListFailedJobsTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListMcpTokensTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListMyAbilitiesTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListPermissionsTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListRolesTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ListTasksTool;
use CleaniqueCoders\LaravelMcpKit\Tools\QueueStatusTool;
use CleaniqueCoders\LaravelMcpKit\Tools\RetryFailedJobTool;
use CleaniqueCoders\LaravelMcpKit\Tools\RevokeMcpTokenTool;
use CleaniqueCoders\LaravelMcpKit\Tools\ScheduledTasksTool;
use CleaniqueCoders\LaravelMcpKit\Tools\SearchLogsTool;
use CleaniqueCoders\LaravelMcpKit\Tools\SystemHealthTool;
use CleaniqueCoders\LaravelMcpKit\Tools\TailLogsTool;
use CleaniqueCoders\LaravelMcpKit\Tools\WhoAmITool;

/**
 * The single source of truth for what the MCP server exposes.
 *
 * The point of the kit is generic, opt-in tooling: every Tier-1 tool is always
 * on (zero dependencies), and every Tier-2 tool auto-registers ONLY when its
 * backing package is installed (and, where relevant, its table exists). A host
 * gets exactly the tools its stack can support — no fatal "class not found",
 * no half-wired feature. {@see TaskServer} reads this in boot().
 */
class ToolRegistry
{
    /**
     * @return array<int, class-string>
     */
    public static function tools(): array
    {
        return array_values(array_filter([
            // Tier 0 — the reference task domain.
            ListTasksTool::class,
            GetTaskTool::class,
            CreateTaskTool::class,
            CompleteTaskTool::class,
            AssignTaskTool::class,

            // Tier 1 — pure-generic ops tools (always on).
            WhoAmITool::class,
            ListMyAbilitiesTool::class,
            SystemHealthTool::class,
            TailLogsTool::class,
            SearchLogsTool::class,
            ExportLogsTool::class,
            ListFailedJobsTool::class,
            RetryFailedJobTool::class,
            QueueStatusTool::class,
            ScheduledTasksTool::class,

            // Tier 2 — registered only when the backing package is present.
            IssueMcpTokenTool::isAvailable() ? IssueMcpTokenTool::class : null,
            ListMcpTokensTool::isAvailable() ? ListMcpTokensTool::class : null,
            RevokeMcpTokenTool::isAvailable() ? RevokeMcpTokenTool::class : null,
            ListAuditsTool::isAvailable() ? ListAuditsTool::class : null,
            ListActivitiesTool::isAvailable() ? ListActivitiesTool::class : null,
            ListRolesTool::isAvailable() ? ListRolesTool::class : null,
            ListPermissionsTool::isAvailable() ? ListPermissionsTool::class : null,
            GetUserPermissionsTool::isAvailable() ? GetUserPermissionsTool::class : null,
        ]));
    }

    /**
     * The Tier-2 tools — those registered only when a backing package (and
     * table) is present. The single list both {@see tools()} gates and
     * `mcp-kit:doctor` reports against.
     *
     * @return array<int, class-string>
     */
    public static function packageGatedTools(): array
    {
        return [
            IssueMcpTokenTool::class,
            ListMcpTokensTool::class,
            RevokeMcpTokenTool::class,
            ListAuditsTool::class,
            ListActivitiesTool::class,
            ListRolesTool::class,
            ListPermissionsTool::class,
            GetUserPermissionsTool::class,
        ];
    }

    /**
     * @return array<int, class-string>
     */
    public static function resources(): array
    {
        return [
            TaskBoardResource::class,
        ];
    }

    /**
     * @return array<int, class-string>
     */
    public static function prompts(): array
    {
        return [
            TriageRunbookPrompt::class,
            SupportRunbookPrompt::class,
        ];
    }
}
