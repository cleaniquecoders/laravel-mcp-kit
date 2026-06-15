<?php

namespace CleaniqueCoders\LaravelMcpKit\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Issues a Sanctum personal access token for the HTTP (remote) transport
 * and prints the ready-to-paste `claude mcp add` command.
 *
 * This is the self-service path for clients that CAN send a custom header
 * (Claude Code / Desktop). For clients that cannot (claude.ai connectors),
 * use the OAuth flow instead — see the auth docs.
 */
class IssueTokenCommand extends Command
{
    protected $signature = 'mcp-kit:token
        {email? : The email of the user to issue a token for}
        {--name=mcp-kit : A label for the token}';

    protected $description = 'Issue a Sanctum access token for the MCP Kit HTTP endpoint';

    public function handle(): int
    {
        if (! config('mcp-kit.enabled', true)) {
            $this->error('MCP Kit is disabled (mcp-kit.enabled). Enable it before issuing tokens.');

            return self::FAILURE;
        }

        if (! config('mcp-kit.web.enabled', true)) {
            $this->error('The HTTP transport is disabled (mcp-kit.web.enabled). Tokens only apply to the HTTP endpoint.');

            return self::FAILURE;
        }

        $email = $this->argument('email') ?: $this->ask('User email');

        $user = $this->resolveUser($email);

        if ($user === null) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        if (! $this->canUseTools($user)) {
            $this->error('That user holds neither the view-tasks nor the manage-tasks ability — a token would be useless.');

            return self::FAILURE;
        }

        if (! method_exists($user, 'createToken')) {
            $this->error('The user model is missing Laravel\Sanctum\HasApiTokens. Add the trait to issue tokens.');

            return self::FAILURE;
        }

        $token = $user->createToken($this->option('name'))->plainTextToken;

        $this->info('Token issued. It is shown once — copy it now.');
        $this->newLine();
        $this->line("  <comment>{$token}</comment>");
        $this->newLine();

        $this->line('Add it to Claude with:');
        $this->newLine();
        $this->line(sprintf(
            '  <info>claude mcp add --transport http mcp-kit %s</info> \\',
            $this->endpoint()
        ));
        $this->line(sprintf(
            '    <info>--header "Authorization: Bearer %s"</info>',
            $token
        ));

        return self::SUCCESS;
    }

    protected function resolveUser(string $email): ?Authenticatable
    {
        /** @var class-string<Model> $model */
        $model = config('auth.providers.users.model', 'App\\Models\\User');

        $user = $model::query()->where('email', $email)->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    protected function canUseTools(Authenticatable $user): bool
    {
        return Gate::forUser($user)->any([
            config('mcp-kit.abilities.view-tasks', 'mcp-kit.view-tasks'),
            config('mcp-kit.abilities.manage-tasks', 'mcp-kit.manage-tasks'),
        ]);
    }

    protected function endpoint(): string
    {
        if (Route::has('mcp-kit.tasks')) {
            return route('mcp-kit.tasks');
        }

        return url(config('mcp-kit.web.path', 'mcp/tasks'));
    }
}
