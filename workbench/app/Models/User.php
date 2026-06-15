<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Workbench\Database\Factories\UserFactory;

/**
 * The workbench's User. Carries ONLY Sanctum's HasApiTokens (Passport's
 * guard calls withAccessToken() itself — the two token traits cannot be
 * composed on one model). `is_manager` stands in for the host's permission
 * system and drives the demo gates in WorkbenchServiceProvider.
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;

    protected $fillable = ['name', 'email', 'password', 'is_manager'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_manager' => 'bool',
        'password' => 'hashed',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
