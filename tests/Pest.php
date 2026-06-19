<?php

use CleaniqueCoders\LaravelMcpKit\Tests\Fixtures\User;
use CleaniqueCoders\LaravelMcpKit\Tests\OAuthTestCase;
use CleaniqueCoders\LaravelMcpKit\Tests\TestCase;

uses(TestCase::class)->in('Feature');

// OAuth flow tests need the server booted with OAuth on (Passport guard +
// oauthRoutes), so they live in their own tree under tests/OAuth.
uses(OAuthTestCase::class)->in('OAuth');

/**
 * A user that can read tasks (view) but cannot write.
 */
function viewer(): User
{
    return tap(new User(['grants' => ['view']]))->setAttribute('id', 1);
}

/**
 * A user that can both read and write tasks.
 */
function manager(): User
{
    return tap(new User(['grants' => ['view', 'manage']]))->setAttribute('id', 2);
}

/**
 * A user with no grants — every tool should reject them.
 */
function nobody(): User
{
    return tap(new User(['grants' => []]))->setAttribute('id', 3);
}

/**
 * A user holding EVERY ability (grants '*') — for exercising the authorized
 * path of the generic toolbox tools.
 */
function admin(): User
{
    return tap(new User(['grants' => ['*', 'view', 'manage']]))->setAttribute('id', 4);
}

/**
 * A user holding exactly the given ability keys (e.g. ['view-logs']).
 *
 * @param  array<int, string>  $keys
 */
function granted(array $keys, int $id = 5): User
{
    return tap(new User(['grants' => $keys]))->setAttribute('id', $id);
}
