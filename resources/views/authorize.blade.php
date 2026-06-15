{{--
    Minimal OAuth consent screen for the MCP Kit OAuth transport.

    Passport 13 ships no authorization view, so this is provided as a
    publishable stub. Wire it up in a service provider:

        \Laravel\Passport\Passport::authorizationView('mcp-kit::authorize');

    Then publish + restyle it to match your app:

        php artisan vendor:publish --tag="mcp-kit-views"

    Passport passes in $client, $user, $scopes, $request and $authToken.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorization Request</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f5f5f7; margin: 0; padding: 2rem; }
        .card { max-width: 28rem; margin: 4rem auto; background: #fff; border-radius: .75rem;
                box-shadow: 0 1px 3px rgba(0,0,0,.1); padding: 2rem; }
        h1 { font-size: 1.25rem; margin: 0 0 1rem; }
        p { color: #444; line-height: 1.5; }
        ul { color: #444; }
        .actions { display: flex; gap: .75rem; margin-top: 1.5rem; }
        button { flex: 1; padding: .6rem 1rem; border: 0; border-radius: .5rem; font-size: 1rem;
                 cursor: pointer; }
        .approve { background: #2563eb; color: #fff; }
        .deny { background: #e5e7eb; color: #111; }
        .note { font-size: .8rem; color: #777; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Authorization Request</h1>

        <p><strong>{{ $client->name }}</strong> is requesting access to your account
            (<strong>{{ $user->email ?? $user->getAuthIdentifier() }}</strong>).</p>

        @if (count($scopes) > 0)
            <p>This will grant the following scopes:</p>
            <ul>
                @foreach ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @endforeach
            </ul>
        @endif

        <p class="note">Access uses your existing permissions — nothing more. Each MCP tool is
            still gated by the abilities on your account.</p>

        <div class="actions">
            <form method="post" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="approve">Authorize</button>
            </form>

            <form method="post" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('delete')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="deny">Cancel</button>
            </form>
        </div>
    </div>
</body>
</html>
