<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The single most useful generic primitive: it answers "who am I, and what may
 * I do?" — the exact auth check every other tool already performs, exposed so
 * an agent can self-orient before attempting a gated tool.
 *
 * Needs only an authenticated user (no specific ability), so an agent can
 * always discover its own footing.
 */
#[Name('whoami')]
#[Description('Return the identity of the authenticated token holder (uuid/name/email) and the MCP Kit abilities they hold. Use this first to discover what you are allowed to do.')]
#[IsReadOnly]
class WhoAmITool extends McpKitTool
{
    protected function requiresAbility(): bool
    {
        return false;
    }

    protected function ability(): string
    {
        return $this->configuredAbility('whoami');
    }

    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser($request);

        if ($user === null) {
            return $this->unauthorized();
        }

        return Response::json([
            'authenticated' => true,
            'uuid' => $this->publicId($user),
            'name' => $this->attribute($user, 'name'),
            'email' => $this->attribute($user, 'email'),
            'abilities' => $this->grantedAbilities($user),
        ]);
    }

    /**
     * Prefer a public uuid/ulid if the user model exposes one; never leak the
     * raw auto-increment id (the kit's uuid-only convention).
     */
    protected function publicId(object $user): ?string
    {
        foreach (['uuid', 'ulid', 'public_id'] as $key) {
            $value = $this->attribute($user, $key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function attribute(object $user, string $key): mixed
    {
        if ($user instanceof Model) {
            return $user->getAttribute($key);
        }

        return $user->{$key} ?? null;
    }

    /**
     * The configured MCP Kit abilities this user currently holds.
     *
     * @return array<int, string>
     */
    protected function grantedAbilities(object $user): array
    {
        $abilities = (array) config('mcp-kit.abilities', []);
        $granted = [];

        foreach ($abilities as $ability) {
            if (is_string($ability) && method_exists($user, 'can') && $user->can($ability)) {
                $granted[] = $ability;
            }
        }

        return array_values(array_unique($granted));
    }
}
