<?php

namespace CleaniqueCoders\LaravelMcpKit\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

/**
 * Issue a Sanctum personal access token for a user, namespaced to MCP.
 *
 * The token name is forced under the configured prefix so the list/revoke
 * tools can scope strictly to MCP connections — an agent can mint and rotate
 * its own access without ever seeing a user's other application tokens.
 */
class IssueMcpToken
{
    public function __construct(
        protected Authenticatable $user,
        protected string $name = 'default',
    ) {}

    /**
     * @return array{id: string, name: string, token: string}
     */
    public function handle(): array
    {
        $prefix = (string) config('mcp-kit.tokens.prefix', 'mcp-kit');
        $name = str_starts_with($this->name, $prefix.':') ? $this->name : $prefix.':'.$this->name;

        $user = $this->user;

        if (! method_exists($user, 'createToken')) {
            throw new RuntimeException('The user model does not use Laravel\\Sanctum\\HasApiTokens.');
        }

        $plainTextToken = $user->createToken($name)->plainTextToken;

        // Sanctum's plain-text token is "{id}|{hash}". Derive the id from it so
        // we never touch the loosely-typed NewAccessToken->accessToken.
        $id = explode('|', $plainTextToken, 2)[0];

        return ['id' => $id, 'name' => $name, 'token' => $plainTextToken];
    }
}
