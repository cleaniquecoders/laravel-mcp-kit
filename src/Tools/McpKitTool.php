<?php

namespace CleaniqueCoders\LaravelMcpKit\Tools;

use CleaniqueCoders\LaravelMcpKit\Actions\ExportToSignedUrl;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base class for every MCP Kit tool — the pattern that is the point of this
 * package. Three production lessons are baked in:
 *
 *  1. Authorization is per-tool, checked on the token holder. MCP is a third
 *     UI on top of the same Gate abilities the web app uses — it must never
 *     be a back door. A tool the user can't perform returns an error, not
 *     partial data. Resolve the ability from config so a host can remap it.
 *
 *  2. Inputs and outputs speak public identifiers (uuid / code) only. The
 *     internal auto-increment id is never exposed to the agent.
 *
 *  3. Reads are cheap, writes go through Actions, and large or binary results
 *     are handed back as short-lived signed download URLs, never inlined.
 *
 * The abilities are defined by the HOST application (see the README), so the
 * package stays framework-native and does not force a permission package.
 */
abstract class McpKitTool extends Tool
{
    /**
     * The Gate ability required to use this tool, e.g. `mcp-kit.view-tasks`.
     *
     * Read it from `config('mcp-kit.abilities.*')` so hosts can remap it.
     */
    abstract protected function ability(): string;

    /**
     * Whether this tool gates on {@see ability()}. A few primitives (whoami,
     * list_my_abilities) only need an authenticated user so an agent can
     * self-discover what it may do — they override this to false.
     */
    protected function requiresAbility(): bool
    {
        return true;
    }

    /**
     * Return the authenticated user when they may use this tool, or null.
     *
     * Gate-first: every handle() starts by calling this and bailing with
     * {@see unauthorized()} when it returns null.
     */
    protected function authorizedUser(Request $request): ?Authenticatable
    {
        $user = $request->user() ?? $this->localUser();

        if ($user === null) {
            return null;
        }

        if ($this->requiresAbility() && ! $user->can($this->ability())) {
            return null;
        }

        return $user;
    }

    /**
     * The user the stdio transport acts as, from `mcp-kit.local.user`.
     *
     * Only resolved over stdio (console) with no authenticated request user,
     * so an HTTP request that failed auth can never fall through to it.
     */
    protected function localUser(): ?Authenticatable
    {
        $email = config('mcp-kit.local.user');

        if ($email === null || ! App::runningInConsole()) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = config('auth.providers.users.model', 'App\\Models\\User');

        $user = $model::query()->where('email', $email)->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    protected function unauthorized(): Response
    {
        if (! $this->requiresAbility()) {
            return Response::error('Unauthorized — this tool requires an authenticated user.');
        }

        return Response::error(
            "Unauthorized — this tool requires the '{$this->ability()}' ability."
        );
    }

    /**
     * Resolve a configured ability name, defaulting to `mcp-kit.<key>`.
     */
    protected function configuredAbility(string $key): string
    {
        return (string) config("mcp-kit.abilities.{$key}", "mcp-kit.{$key}");
    }

    /**
     * Build the standard paginated payload: a mapped collection plus a compact
     * pagination block. Keeps every list tool's output shape identical.
     *
     * @param  LengthAwarePaginator<int, covariant mixed>  $paginator
     * @param  callable(mixed): array<string, mixed>  $map
     * @return array<string, mixed>
     */
    protected function paginatedSummary(LengthAwarePaginator $paginator, callable $map): array
    {
        return [
            'data' => collect($paginator->items())->map($map)->values()->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Persist export contents to the configured disk and return a short-lived
     * signed download URL — the safe way to hand an agent a large or binary
     * artifact (logs, reports) without inlining it in the response.
     */
    protected function download(string $contents, string $filename, ?int $ttlMinutes = null): string
    {
        return (new ExportToSignedUrl($contents, $filename, ttlMinutes: $ttlMinutes))->handle();
    }
}
