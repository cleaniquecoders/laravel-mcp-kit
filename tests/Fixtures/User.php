<?php

namespace CleaniqueCoders\LaravelMcpKit\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A throwaway user for the test suite. `grants` drives the demo gates
 * defined in TestCase — it stands in for whatever permission system the
 * host app uses (roles, spatie/laravel-permission, etc.).
 *
 * Carries Sanctum's HasApiTokens so the `mcp-kit:token` command and the
 * HTTP-auth tests can issue real personal access tokens. Note we use ONLY
 * the Sanctum trait — Sanctum's and Passport's HasApiTokens cannot be
 * composed on the same model (incompatible $accessToken property types);
 * Passport's guard calls withAccessToken() itself.
 *
 * @property array<int, string> $grants
 */
class User extends Authenticatable
{
    use HasApiTokens;

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['grants' => 'array'];
}
