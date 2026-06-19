<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * List the application's roles (spatie/laravel-permission). Auto-registers only
 * when the package is installed; the Role model is resolved from the package's
 * own config so a custom model is honoured.
 */
#[Name('list_roles')]
#[Description('List RBAC roles (spatie/laravel-permission) with their guard and permission counts.')]
#[IsReadOnly]
class ListRolesTool extends McpKitTool
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
        $model = config('permission.models.role', 'Spatie\\Permission\\Models\\Role');

        return $model;
    }

    protected function ability(): string
    {
        return $this->configuredAbility('view-permissions');
    }

    public function handle(Request $request): Response
    {
        if (! static::isAvailable()) {
            return Response::error('Role listing is unavailable — spatie/laravel-permission is not installed.');
        }

        if ($this->authorizedUser($request) === null) {
            return $this->unauthorized();
        }

        $model = static::model();

        $roles = $model::query()
            ->withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Model $role): array => [
                'name' => $role->getAttribute('name'),
                'guard_name' => $role->getAttribute('guard_name'),
                'permissions_count' => (int) $role->getAttribute('permissions_count'),
            ])
            ->all();

        return Response::json([
            'count' => count($roles),
            'roles' => $roles,
        ]);
    }
}
