<?php

namespace CleaniqueCoders\LaravelMcpKit\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/**
 * A PROMPT, not a tool.
 *
 * Prompts are reusable, parameterised instruction templates the client
 * can offer the user (e.g. a slash command). This one encodes a triage
 * runbook so every operator triages tasks the same disciplined way —
 * read first, propose, and stop at the human gate before any write.
 */
#[Name('triage_runbook')]
#[Title('Task Triage Runbook')]
#[Description('A step-by-step runbook for triaging open tasks using the read tools, then proposing actions for human approval.')]
class TriageRunbookPrompt extends Prompt
{
    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'assignee',
                description: 'Optional: focus the triage on one assignee.',
                required: false,
            ),
        ];
    }

    public function handle(Request $request): Response
    {
        $assignee = $request->get('assignee');
        $scope = is_string($assignee) && $assignee !== ''
            ? "the tasks assigned to **{$assignee}**"
            : 'all open tasks';

        return Response::text(<<<PROMPT
        You are triaging {$scope} in the MCP Kit task board.

        Follow this runbook exactly:

        1. Call `list_tasks` (filter status=open) to see what is outstanding.
           Read the `task_board` resource if you need the full picture.
        2. For anything ambiguous, call `get_task` to read the full description
           before forming an opinion. Never guess — ground every claim in a
           tool result.
        3. Produce a short triage summary: what is stale, what is ready to
           close, what is blocked.
        4. PROPOSE the writes you would make (create_task / complete_task) as a
           numbered list — but DO NOT call any write tool yet.
        5. Stop and wait for the human to approve. Only after explicit approval
           do you call the write tools, one at a time, confirming each result.

        This is the human-in-the-loop gate: reads are free, writes wait for a
        human. Begin with step 1.
        PROMPT);
    }
}
