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
 * The generic support runbook — the read-first, human-gated investigation flow
 * we re-wrote on every production MCP server before extracting it here. Unlike
 * TriageRunbookPrompt (task-specific), this drives an operator through ANY
 * incident using the generic ops tools: orient, observe, diagnose, propose,
 * then stop at the human gate before any state change.
 */
#[Name('support_runbook')]
#[Title('Support Runbook')]
#[Description('A disciplined read-first, human-gated runbook for investigating an issue with the generic ops tools (identity, health, logs, jobs) before proposing any change.')]
class SupportRunbookPrompt extends Prompt
{
    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'symptom',
                description: 'Optional: the symptom or report to investigate (e.g. "queue is backing up").',
                required: false,
            ),
        ];
    }

    public function handle(Request $request): Response
    {
        $symptom = $request->get('symptom');
        $focus = is_string($symptom) && $symptom !== ''
            ? "the reported issue: **{$symptom}**"
            : 'the current health of the system';

        return Response::text(<<<PROMPT
        You are an operator investigating {$focus}. Follow this runbook exactly.

        1. ORIENT. Call `whoami` and `list_my_abilities` to confirm who you are
           and which tools you are permitted to use. Do not attempt a tool you
           are not granted.
        2. OBSERVE. Establish the facts before forming any theory:
           - `system_health` for database / cache / queue / storage and any
             app-defined connectivity checks.
           - `queue_status` and `list_failed_jobs` if anything is queue-related.
           - `tail_logs` (filter by level=error) and `search_logs` for the
             relevant error text or time window. Use `export_logs` if you need
             to hand a slice to a human.
           - `scheduled_tasks` if a missed/late job is suspected.
        3. DIAGNOSE. Summarise what the evidence shows: what is healthy, what is
           degraded, the most likely cause. Ground every claim in a tool result
           — never guess.
        4. PROPOSE. List the remediation steps as a numbered plan. Mark any that
           use a state-changing tool (e.g. retry_failed_job, issue/revoke
           tokens) clearly — but DO NOT call them yet.
        5. STOP at the human gate. Only after explicit approval do you run the
           state-changing tools, one at a time, confirming each result before
           the next.

        Reads are free; writes wait for a human. Begin with step 1.
        PROMPT);
    }
}
