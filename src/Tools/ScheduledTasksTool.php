<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

/**
 * List the registered scheduler entries (`app/Console` / `routes/console.php`):
 * each task's summary, cron expression, and next run time. Read-only — it
 * inspects the schedule, it does not run anything.
 */
#[Name('scheduled_tasks')]
#[Description('List the application scheduler entries with their cron expression and next run time. Read-only.')]
#[IsReadOnly]
class ScheduledTasksTool extends McpKitTool
{
    protected function ability(): string
    {
        return $this->configuredAbility('view-system');
    }

    public function handle(Request $request): Response
    {
        if ($this->authorizedUser($request) === null) {
            return $this->unauthorized();
        }

        $schedule = app(Schedule::class);

        $tasks = array_map($this->present(...), $schedule->events());

        return Response::json([
            'count' => count($tasks),
            'tasks' => array_values($tasks),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(Event $event): array
    {
        $nextRun = null;

        try {
            $nextRun = $event->nextRunDate()->toIso8601String();
        } catch (Throwable) {
            // closures with custom filters can refuse a next-run calculation
        }

        return [
            'summary' => $event->getSummaryForDisplay(),
            'expression' => $event->getExpression(),
            'description' => $event->description,
            'timezone' => $this->timezone($event),
            'next_run' => $nextRun,
            'without_overlapping' => $event->withoutOverlapping,
        ];
    }

    protected function timezone(Event $event): ?string
    {
        $timezone = $event->timezone;

        if ($timezone instanceof \DateTimeZone) {
            return $timezone->getName();
        }

        return $timezone;
    }
}
