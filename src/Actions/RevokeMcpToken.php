<?php

namespace CleaniqueCoders\LaravelMcpKit\Actions;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\Sanctum;

/**
 * Revoke one of a user's MCP tokens by id.
 *
 * Two guard rails make this safe to expose to an agent: it only ever looks at
 * the GIVEN user's own tokens (scoped by tokenable id + type), and it refuses
 * to delete anything outside the configured MCP prefix — so it can never nuke
 * a user's other app tokens.
 */
class RevokeMcpToken
{
    public function __construct(
        protected Model $user,
        protected int|string $tokenId,
    ) {}

    /**
     * @return bool true when an MCP-scoped token was found and revoked.
     */
    public function handle(): bool
    {
        $prefix = (string) config('mcp-kit.tokens.prefix', 'mcp-kit');

        /** @var class-string<Model> $tokenModel */
        $tokenModel = Sanctum::personalAccessTokenModel();

        $token = $tokenModel::query()
            ->whereKey($this->tokenId)
            ->where('tokenable_id', $this->user->getKey())
            ->where('tokenable_type', $this->user->getMorphClass())
            ->first();

        if ($token === null || ! str_starts_with((string) $token->getAttribute('name'), $prefix.':')) {
            return false;
        }

        $token->delete();

        return true;
    }
}
