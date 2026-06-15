<?php

use CleaniqueCoders\LaravelMcpKit\Tests\Fixtures\User;
use CleaniqueCoders\LaravelMcpKit\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

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
