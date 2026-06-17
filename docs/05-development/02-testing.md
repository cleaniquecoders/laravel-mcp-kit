# Testing

The suite uses Pest, plus larastan and Pint for quality.

## Commands

```bash
composer test           # run the Pest suite
composer test-coverage  # suite with coverage
composer format         # Pint (run before committing)
composer analyse        # larastan, level 5
```

Run a single test or file:

```bash
vendor/bin/pest --filter='create_task'
vendor/bin/pest tests/Feature/WriteToolsTest.php
```

## How tool tests are written

Tools are driven through the server with an acting user:

```php
TaskServer::actingAs($user)->tool(CreateTaskTool::class, [...]);
```

Each test asserts the **schema**, **authorization** (`assertHasErrors` for the unauthorized path), and
the **side-effect** (DB). Grab actors from the `viewer()` / `manager()` / `nobody()` helpers in
`tests/Pest.php` to cover the gated paths.

The full OAuth 2.1 authorization-code + PKCE flow is covered end to end in
`tests/OAuth/AuthorizationCodeFlowTest.php` (consent → token exchange → authenticated MCP call).

## Gotcha (testbench)

> When testing an MCP server inside a package, you must register
> `Laravel\Mcp\Server\McpServiceProvider` in your `getPackageProviders()`. It registers the
> `resolving(Request::class)` callback that copies tool arguments into the injected `Request`. Without
> it, every tool sees **empty arguments** (validation fails, filters are ignored) even though the
> server otherwise responds. See `tests/TestCase.php`.

## Next Steps

- [Workbench](01-workbench.md) — run the server interactively.
- [Conventions](../02-architecture/02-conventions.md) — what the tests assert.
