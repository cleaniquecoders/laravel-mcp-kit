<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Resolve a user's effective roles and permissions (spatie/laravel-permission).
 * Defaults to the authenticated user; an optional email targets another user.
 * Auto-registers only when the package is installed.
 */
#[Name('get_user_permissions')]
#[Description("Get a user's roles and effective permissions (spatie/laravel-permission). Defaults to the authenticated user; pass an email to inspect another user.")]
#[IsReadOnly]
class GetUserPermissionsTool extends McpKitTool
{
    public static function isAvailable(): bool
    {
        // Same condition as the other RBAC tools, so the three register
        // together: the configured (or default Spatie) Role model is loadable.
        /** @var class-string $role */
        $role = config('permission.models.role', 'Spatie\\Permission\\Models\\Role');

        return class_exists($role);
    }

    protected function ability(): string
    {
        return $this->configuredAbility('view-permissions');
    }

    public function handle(Request $request): Response
    {
        if (! static::isAvailable()) {
            return Response::error('Permission inspection is unavailable — spatie/laravel-permission is not installed.');
        }

        $actor = $this->authorizedUser($request);

        if ($actor === null) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $target = isset($validated['email'])
            ? $this->resolveByEmail($validated['email'])
            : $actor;

        if ($target === null) {
            return Response::error('No user found with that email.');
        }

        if (! method_exists($target, 'getRoleNames') || ! method_exists($target, 'getAllPermissions')) {
            return Response::error('The target user model does not use spatie/laravel-permission (HasRoles).');
        }

        /** @var Collection<int, string> $roles */
        $roles = $target->getRoleNames();

        /** @var Collection<int, Model> $permissions */
        $permissions = $target->getAllPermissions();

        return Response::json([
            'email' => $this->attribute($target, 'email'),
            'roles' => $roles->values()->all(),
            'permissions' => $permissions->map(fn (Model $p) => $p->getAttribute('name'))->values()->all(),
        ]);
    }

    protected function resolveByEmail(string $email): ?Authenticatable
    {
        /** @var class-string<Model> $model */
        $model = config('auth.providers.users.model', 'App\\Models\\User');

        $user = $model::query()->where('email', $email)->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    protected function attribute(object $user, string $key): mixed
    {
        if ($user instanceof Model) {
            return $user->getAttribute($key);
        }

        return $user->{$key} ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'email' => $schema->string()
                ->description('Email of the user to inspect. Defaults to the authenticated user.'),
        ];
    }
}
