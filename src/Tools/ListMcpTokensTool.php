<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Sanctum\Sanctum;

/**
 * List the authenticated user's MCP-scoped tokens (id, name, abilities, last
 * used, created). Only tokens under the configured MCP prefix are shown, so
 * this never discloses a user's other application tokens. Auto-registers only
 * when laravel/sanctum is installed.
 */
#[Name('list_mcp_tokens')]
#[Description("List the authenticated user's MCP-scoped Sanctum tokens (id, name, last used, created). Use the id with revoke_mcp_token.")]
#[IsReadOnly]
class ListMcpTokensTool extends McpKitTool
{
    public static function isAvailable(): bool
    {
        return class_exists(Sanctum::class)
            && Schema::hasTable('personal_access_tokens');
    }

    protected function ability(): string
    {
        return $this->configuredAbility('manage-tokens');
    }

    public function handle(Request $request): Response
    {
        if (! static::isAvailable()) {
            return Response::error('Token management is unavailable — laravel/sanctum is not installed or not migrated.');
        }

        $user = $this->authorizedUser($request);

        if ($user === null) {
            return $this->unauthorized();
        }

        if (! $user instanceof Model) {
            return Response::error('The authenticated user is not an Eloquent model with tokens.');
        }

        $prefix = (string) config('mcp-kit.tokens.prefix', 'mcp-kit');

        /** @var class-string<Model> $tokenModel */
        $tokenModel = Sanctum::personalAccessTokenModel();

        $tokens = $tokenModel::query()
            ->where('tokenable_id', $user->getKey())
            ->where('tokenable_type', $user->getMorphClass())
            ->where('name', 'like', $prefix.':%')
            ->latest()
            ->get()
            ->map(fn (Model $token): array => [
                'id' => $token->getKey(),
                'name' => $token->getAttribute('name'),
                'abilities' => $token->getAttribute('abilities'),
                'last_used_at' => $this->iso($token->getAttribute('last_used_at')),
                'created_at' => $this->iso($token->getAttribute('created_at')),
            ])
            ->all();

        return Response::json([
            'count' => count($tokens),
            'tokens' => $tokens,
        ]);
    }

    protected function iso(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return $value !== null ? (string) $value : null;
    }
}
