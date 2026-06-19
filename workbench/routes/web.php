<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

Route::get('/', fn () => response()->json([
    'app' => 'MCP Kit Workbench',
    'mcp_endpoint' => route('mcp-kit.tasks'),
    'oauth_enabled' => (bool) config('mcp-kit.web.oauth.enabled'),
    'settings_ui' => url('/mcp').' (auto-signs you in as the demo manager)',
    'hint' => 'Issue a token: `composer mcp-token`. Or connect via OAuth (visit /login first).',
]));

/*
 * MCP settings UI harness (issue #16). Sign in at /login (as the demo manager,
 * who holds `manage-mcp`) then open /mcp to flip the runtime toggle and review
 * the effective config, health, and registered tools in the browser.
 */
Route::get('/mcp', fn () => view('mcp'))
    ->middleware(['web', 'auth'])
    ->name('mcp.settings');

/*
 * Demo-only convenience login so Passport's OAuth consent screen has an
 * authenticated session. NEVER ship anything like this in a real app —
 * a real app authenticates via its own login (Fortify, Breeze, SSO, …).
 *
 * `intended()` returns you to whatever you were trying to reach (e.g. /mcp
 * bounces here then back); visiting /login directly falls back to the settings
 * page rather than the bare JSON welcome.
 */
Route::get('/login', function () {
    Auth::login(User::where('email', 'manager@example.com')->firstOrFail());

    return redirect()->intended('/mcp');
})->name('login');
