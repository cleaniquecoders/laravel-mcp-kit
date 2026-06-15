<?php

namespace CleaniqueCoders\LaravelMcpKit\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A throwaway user for the test suite. `grants` drives the demo gates
 * defined in TestCase — it stands in for whatever permission system the
 * host app uses (roles, spatie/laravel-permission, etc.).
 *
 * @property array<int, string> $grants
 */
class User extends Authenticatable
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['grants' => 'array'];
}
