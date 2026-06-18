# Installation

Install the package, publish its files, and define the two Gate abilities every tool checks.

## Requirements

- PHP 8.4+
- Laravel 11, 12, or 13
- `laravel/mcp` ^0.8

## Install

```bash
composer require cleaniquecoders/laravel-mcp-kit
```

Run the installer — it publishes the config and migration in one step:

```bash
php artisan mcp-kit:install          # token-only (Sanctum) transport
php artisan mcp-kit:install --oauth  # also wire the OAuth 2.1 flow
php artisan migrate
```

| Option | Effect |
|--------|--------|
| _(none)_ | Publishes `config/mcp-kit.php` and the `mcp_kit_tasks` migration |
| `--oauth` | Also publishes the consent view and generates Passport keys |
| `--ui` | Also publishes the Livewire + Flux token-management UI |
| `--force` | Overwrites any files that were already published |

> **Tip**: Prefer to do it by hand? `vendor:publish --tag="mcp-kit-config"` and
> `--tag="mcp-kit-migrations"` publish the same files.

## Define the gates (required)

Every tool checks a Gate ability. The package does **not** ship a permission system — define the two
abilities however your app does authorization (a Policy, `Gate::define`, or
`spatie/laravel-permission`). The simplest wiring, in a service provider:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('mcp-kit.view-tasks', fn ($user) => $user->isStaff());
Gate::define('mcp-kit.manage-tasks', fn ($user) => $user->isAdmin());
```

| Ability | Used by |
|---|---|
| `mcp-kit.view-tasks` | `list_tasks`, `get_task`, `task_board` resource |
| `mcp-kit.manage-tasks` | `create_task`, `complete_task`, `assign_task` |

## Next Steps

- [Quick Start](02-quick-start.md) — seed data and connect a client.
- [Configuration](../04-configuration/README.md) — the full config reference.
