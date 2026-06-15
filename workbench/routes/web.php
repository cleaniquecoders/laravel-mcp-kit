<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

Route::get('/', fn () => response()->json([
    'app' => 'MCP Kit Workbench',
    'mcp_endpoint' => route('mcp-kit.tasks'),
    'oauth_enabled' => (bool) config('mcp-kit.web.oauth.enabled'),
    'hint' => 'Issue a token: `composer mcp-token`. Or connect via OAuth (visit /login first).',
]));

/*
 * Demo-only convenience login so Passport's OAuth consent screen has an
 * authenticated session. NEVER ship anything like this in a real app —
 * a real app authenticates via its own login (Fortify, Breeze, SSO, …).
 */
Route::get('/login', function () {
    Auth::login(User::where('email', 'manager@example.com')->firstOrFail());

    return redirect()->intended('/');
})->name('login');
