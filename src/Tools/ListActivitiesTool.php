<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Read the activity log recorded by spatie/laravel-activitylog. Auto-registers
 * only when the package is installed; the Activity model is resolved from the
 * package's own config.
 *
 * Filter by log name, subject type, causer, event and date.
 */
#[Name('list_activities')]
#[Description('List activity-log records (spatie/laravel-activitylog): log name, description, subject, causer, changes and time. Filter by log/subject/causer/event/date. Paginated.')]
#[IsReadOnly]
class ListActivitiesTool extends McpKitTool
{
    public static function isAvailable(): bool
    {
        return class_exists(static::model());
    }

    /**
     * @return class-string<Model>
     */
    protected static function model(): string
    {
        /** @var class-string<Model> $model */
        $model = config('activitylog.activity_model', 'Spatie\\Activitylog\\Models\\Activity');

        return $model;
    }

    protected function ability(): string
    {
        return $this->configuredAbility('view-activities');
    }

    public function handle(Request $request): Response
    {
        if (! static::isAvailable()) {
            return Response::error('Activity reading is unavailable — spatie/laravel-activitylog is not installed.');
        }

        if ($this->authorizedUser($request) === null) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'log_name' => ['nullable', 'string', 'max:255'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'causer_id' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $model = static::model();

        $paginator = $model::query()
            ->when($validated['log_name'] ?? null, fn ($q, $v) => $q->where('log_name', $v))
            ->when($validated['subject_type'] ?? null, fn ($q, $v) => $q->where('subject_type', $v))
            ->when($validated['causer_id'] ?? null, fn ($q, $v) => $q->where('causer_id', $v))
            ->when($validated['event'] ?? null, fn ($q, $v) => $q->where('event', $v))
            ->when($validated['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($validated['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate(perPage: 20, page: (int) ($validated['page'] ?? 1));

        return Response::json(
            $this->paginatedSummary($paginator, $this->summary(...))
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(Model $activity): array
    {
        $properties = $activity->getAttribute('properties');
        $properties = $properties instanceof Collection
            ? $properties->toArray()
            : (is_array($properties) ? $properties : []);

        return [
            'log_name' => $activity->getAttribute('log_name'),
            'description' => $activity->getAttribute('description'),
            'event' => $activity->getAttribute('event'),
            'subject_type' => $activity->getAttribute('subject_type'),
            'subject_id' => $activity->getAttribute('subject_id'),
            'causer_type' => $activity->getAttribute('causer_type'),
            'causer_id' => $activity->getAttribute('causer_id'),
            'changes' => array_keys($properties),
            'created_at' => $this->iso($activity->getAttribute('created_at')),
        ];
    }

    protected function iso(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return $value !== null ? (string) $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'log_name' => $schema->string()->description('Filter by log name.'),
            'subject_type' => $schema->string()->description('Filter by subject model class.'),
            'causer_id' => $schema->string()->description('Filter by the causer (actor) id.'),
            'event' => $schema->string()->description('Filter by event (created, updated, deleted).'),
            'from' => $schema->string()->description('Only activity on or after this date (YYYY-MM-DD).'),
            'to' => $schema->string()->description('Only activity on or before this date (YYYY-MM-DD).'),
            'page' => $schema->integer()->description('Page number, starting at 1.'),
        ];
    }
}
