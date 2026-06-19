<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Read the audit trail recorded by owen-it/laravel-auditing. Auto-registers
 * only when that package is installed — the kit never forces the dependency.
 *
 * Filter by auditable model/id, actor, event, and date. The model class is
 * resolved from the package's own config so a host's custom Audit model is
 * honoured.
 */
#[Name('list_audits')]
#[Description('List audit-trail records (owen-it/laravel-auditing): event, audited model, actor, changed values and time. Filter by model type/id, actor, event and date. Paginated.')]
#[IsReadOnly]
class ListAuditsTool extends McpKitTool
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
        $model = config('audit.implementation', 'OwenIt\\Auditing\\Models\\Audit');

        return $model;
    }

    protected function ability(): string
    {
        return $this->configuredAbility('view-audits');
    }

    public function handle(Request $request): Response
    {
        if (! static::isAvailable()) {
            return Response::error('Audit reading is unavailable — owen-it/laravel-auditing is not installed.');
        }

        if ($this->authorizedUser($request) === null) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'auditable_type' => ['nullable', 'string', 'max:255'],
            'auditable_id' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $model = static::model();

        $paginator = $model::query()
            ->when($validated['auditable_type'] ?? null, fn ($q, $v) => $q->where('auditable_type', $v))
            ->when($validated['auditable_id'] ?? null, fn ($q, $v) => $q->where('auditable_id', $v))
            ->when($validated['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
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
    protected function summary(Model $audit): array
    {
        $new = $audit->getAttribute('new_values');
        $new = is_array($new) ? $new : (array) json_decode((string) $new, true);

        return [
            'event' => $audit->getAttribute('event'),
            'auditable_type' => $audit->getAttribute('auditable_type'),
            'auditable_id' => $audit->getAttribute('auditable_id'),
            'user_type' => $audit->getAttribute('user_type'),
            'user_id' => $audit->getAttribute('user_id'),
            'changed' => array_keys($new),
            'created_at' => $this->iso($audit->getAttribute('created_at')),
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
            'auditable_type' => $schema->string()->description('Filter by the audited model class (e.g. App\\Models\\User).'),
            'auditable_id' => $schema->string()->description('Filter by the audited record id.'),
            'user_id' => $schema->string()->description('Filter by the acting user id.'),
            'event' => $schema->string()->description('Filter by event (created, updated, deleted, restored).'),
            'from' => $schema->string()->description('Only audits on or after this date (YYYY-MM-DD).'),
            'to' => $schema->string()->description('Only audits on or before this date (YYYY-MM-DD).'),
            'page' => $schema->integer()->description('Page number, starting at 1.'),
        ];
    }
}
