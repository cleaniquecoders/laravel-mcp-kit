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
 * List the application's permissions (spatie/laravel-permission), optionally
 * filtered by guard. Auto-registers only when the package is installed.
 */
#[Name('list_permissions')]
#[Description('List RBAC permissions (spatie/laravel-permission) with their guard, optionally filtered by guard name.')]
#[IsReadOnly]
class ListPermissionsTool extends McpKitTool
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
        $model = config('permission.models.permission', 'Spatie\\Permission\\Models\\Permission');

        return $model;
    }

    protected function ability(): string
    {
        return $this->configuredAbility('view-permissions');
    }

    public function handle(Request $request): Response
    {
        if (! static::isAvailable()) {
            return Response::error('Permission listing is unavailable — spatie/laravel-permission is not installed.');
        }

        if ($this->authorizedUser($request) === null) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'guard' => ['nullable', 'string', 'max:255'],
        ]);

        $model = static::model();

        $permissions = $model::query()
            ->when($validated['guard'] ?? null, fn ($q, $v) => $q->where('guard_name', $v))
            ->orderBy('name')
            ->get()
            ->map(fn (Model $permission): array => [
                'name' => $permission->getAttribute('name'),
                'guard_name' => $permission->getAttribute('guard_name'),
            ])
            ->all();

        return Response::json([
            'count' => count($permissions),
            'permissions' => $permissions,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'guard' => $schema->string()
                ->description('Filter permissions by guard name (e.g. web, api).'),
        ];
    }
}
