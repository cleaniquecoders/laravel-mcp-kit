<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The full picture of what this token holder may and may NOT do: every MCP Kit
 * ability the kit knows about, each marked granted or denied. Where `whoami`
 * lists only what you hold, this also surfaces what exists but is withheld —
 * so an agent understands the shape of the gate, not just its own slice.
 *
 * Authenticated-only, like `whoami`.
 */
#[Name('list_my_abilities')]
#[Description('List every MCP Kit ability and whether the authenticated user holds it. Surfaces both granted and denied abilities so you know the full gate before attempting a tool.')]
#[IsReadOnly]
class ListMyAbilitiesTool extends McpKitTool
{
    protected function requiresAbility(): bool
    {
        return false;
    }

    protected function ability(): string
    {
        return $this->configuredAbility('list-my-abilities');
    }

    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser($request);

        if ($user === null) {
            return $this->unauthorized();
        }

        $abilities = (array) config('mcp-kit.abilities', []);
        $can = method_exists($user, 'can');

        $result = [];
        $granted = 0;

        foreach ($abilities as $key => $ability) {
            if (! is_string($ability)) {
                continue;
            }

            $held = $can && $user->can($ability);
            $granted += $held ? 1 : 0;

            $result[] = [
                'key' => $key,
                'ability' => $ability,
                'granted' => $held,
            ];
        }

        return Response::json([
            'abilities' => $result,
            'granted_count' => $granted,
            'total' => count($result),
        ]);
    }
}
