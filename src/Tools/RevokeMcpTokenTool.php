<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use CleaniqueCoders\LaravelMcpKit\Actions\RevokeMcpToken;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Sanctum\Sanctum;

/**
 * Revoke one of the authenticated user's MCP tokens by id (from
 * list_mcp_tokens). Refuses anything outside the MCP prefix and only ever the
 * caller's own tokens — see the RevokeMcpToken Action. Auto-registers only
 * when laravel/sanctum is installed.
 *
 * A WRITE tool: it destroys a credential, so no #[IsReadOnly].
 */
#[Name('revoke_mcp_token')]
#[Description("Revoke one of the authenticated user's MCP-scoped tokens by id. This destroys a credential — clients should confirm before calling.")]
class RevokeMcpTokenTool extends McpKitTool
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

        $validated = $request->validate([
            'id' => ['required'],
        ]);

        $id = $validated['id'];

        if (! is_string($id) && ! is_int($id)) {
            return Response::error('The token id must be a string or integer.');
        }

        $revoked = (new RevokeMcpToken($user, $id))->handle();

        if (! $revoked) {
            return Response::error('No MCP-scoped token with that id belongs to you.');
        }

        return Response::json([
            'message' => 'Token revoked.',
            'id' => $id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the token to revoke (see list_mcp_tokens).')
                ->required(),
        ];
    }
}
