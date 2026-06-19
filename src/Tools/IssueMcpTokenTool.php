<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use CleaniqueCoders\LaravelMcpKit\Actions\IssueMcpToken;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Sanctum\Sanctum;

/**
 * Issue a fresh MCP access token for the AUTHENTICATED user (never another
 * user). Auto-registers only when laravel/sanctum is installed.
 *
 * A WRITE tool — it creates credentials, so it carries no #[IsReadOnly] and
 * funnels through the IssueMcpToken Action.
 */
#[Name('issue_mcp_token')]
#[Description('Issue a new MCP-scoped Sanctum token for the authenticated user. Returns the plain-text token once. This creates a credential — clients should confirm before calling.')]
class IssueMcpTokenTool extends McpKitTool
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

        if (! method_exists($user, 'createToken')) {
            return Response::error('The authenticated user model does not use Laravel\\Sanctum\\HasApiTokens.');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $result = (new IssueMcpToken($user, $validated['name'] ?? 'default'))->handle();

        return Response::json([
            'message' => 'Token issued. It is shown once — copy it now.',
            'id' => $result['id'],
            'name' => $result['name'],
            'token' => $result['token'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('A label for the token (it is namespaced under the MCP prefix).'),
        ];
    }
}
