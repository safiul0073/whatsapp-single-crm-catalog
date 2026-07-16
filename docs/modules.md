# Modules Guide

This is the canonical guide for modern modules in this repository.

## Runtime Model

Modern modules live in `app/Modules/{Name}` and are discovered by `ModuleRegistry`.

Each modern module has two contracts:

- `module.json` for static metadata (`name`, `alias`, `version`, `providers`, `requires`, `active`, optional `packages`)
- `Module.php` for behavior (`id()`, `permissions()`, `policies()`, `{panel}Navigation()`)

Runtime state and cache:

- `storage/app/module-state.json`: enable/disable overrides
- `bootstrap/cache/modules.php`: cached discovery graph

Precedence is:

1. `module.json` default
2. runtime override file
3. cached resolved graph

## Panel vs Module Boundaries

Panels are shell-only and should own:

- dashboard
- auth/password reset
- profile/session
- 2FA
- shell-wide topbar notifications

Feature pages (CRUD/settings/content screens) belong inside modules:

- controllers: `app/Modules/{Name}/Http/Controllers/{Panel}`
- routes: `app/Modules/{Name}/Routes/{panel}.php`
- views: `app/Modules/{Name}/Resources/views/{panel}`
- lang: `app/Modules/{Name}/Resources/lang/*`
- tests: `app/Modules/{Name}/Tests/*`

## Command Reference

Core lifecycle:

- `php artisan module:list`
- `php artisan module:cache`
- `php artisan module:cache:clear`
- `php artisan module:enable {Module}`
- `php artisan module:disable {Module}`
- `php artisan module:validate {Module?}`

Permissions:

- `php artisan permission:sync`
- `php artisan module:sync-permissions` (alias of `permission:sync`)

Packages (root composer sync):

- `php artisan module:sync-packages`
- `php artisan module:sync-packages --write`
- `php artisan module:sync-packages --check`

Generator:

- `php artisan make:module {Name} --panels=admin --type=crud|settings [--api]`

## Generator Contract

`make:module` only writes inside `app/Modules/{Name}`.

CRUD output includes:

- `module.json`
- `Module.php`
- `Providers/{Name}ServiceProvider.php`
- `Models/{Singular}.php`
- `Services/{Name}Service.php`
- `Policies/{Singular}Policy.php`
- `Tables/{Name}Table.php`
- `Http/Controllers/{Panel}/{Name}Controller.php`
- `Http/Requests/Store{Singular}Request.php`
- `Http/Requests/Update{Singular}Request.php`
- `Routes/{panel}.php`
- `Resources/views/{panel}/{index,create,edit,show}.blade.php`
- `Resources/lang/en/messages.php`
- `Database/{Migrations,Seeders}`
- `Tests/Feature/{Name}ModuleTest.php`

Settings output stays settings-focused (settings controller, config file, settings route/view) and does not generate table schema classes.

Optional API output adds:

- `Http/Controllers/Api/V1/{Singular}Controller.php`
- `Routes/api.php`

## Permissions and Navigation Source of Truth

For module features:

- permissions are declared in each module descriptor (`Module.php`)
- module navigation is declared in descriptor navigation methods

Central configs remain for shell concerns:

- `config/panels.php`: shell config and shell navigation only
- `config/permissions.php`: role definitions and compatibility data during migration

## Dependency Policy

This repo is a modular monolith with one active Composer manifest:

- use root `composer.json` for vendor packages
- do not create per-module `composer.json` in this repo

Module logical dependencies belong in `module.json`:

```json
{
  "requires": ["Settings", "PaymentGateways"]
}
```

Optional vendor ownership can be declared by module and synchronized to root:

```json
{
  "packages": {
    "require": {
      "stripe/stripe-php": "^20.0"
    },
    "require-dev": {}
  }
}
```

Then apply with:

```bash
php artisan module:sync-packages --write
```

## Instant Use

### Create a CRUD Module in 2 Minutes

Command:

```bash
php artisan make:module Invoice --panels=admin
```

Snippet (`app/Modules/Invoices/Http/Controllers/Admin/InvoicesController.php`):

```php
protected function tableDefinition(Request $request): ?TableDefinition
{
    return InvoicesTable::make();
}
```

Where to edit next:

- `app/Modules/Invoices/Tables/InvoicesTable.php`
- `app/Modules/Invoices/Services/InvoicesService.php`
- `app/Modules/Invoices/Resources/views/admin/index.blade.php`

### Create a Settings Module

Command:

```bash
php artisan make:module PaymentGatewaySettings --panels=admin --type=settings
```

Snippet (`app/Modules/PaymentGatewaySettings/Module.php`):

```php
public function permissions(): array
{
    return [
        'admin' => [
            'payment-gateway-settings.view' => 'View PaymentGatewaySettings',
            'payment-gateway-settings.edit' => 'Edit PaymentGatewaySettings',
        ],
    ];
}
```

Where to edit next:

- `app/Modules/PaymentGatewaySettings/Config/payment-gateway-settings.php`
- `app/Modules/PaymentGatewaySettings/Http/Controllers/Admin/PaymentGatewaySettingsController.php`
- `app/Modules/PaymentGatewaySettings/Resources/views/admin/index.blade.php`

### Add Module Package Declarations and Sync

Command:

```bash
php artisan module:sync-packages --write
```

Snippet (`app/Modules/PaymentGateways/module.json`):

```json
{
  "packages": {
    "require": {
      "stripe/stripe-php": "^20.0"
    }
  }
}
```

Where to edit next:

- `app/Modules/{Name}/module.json`
- root `composer.json` (after sync)
- module service/runtime guards for optional SDKs (`class_exists(...)`)

## Validation and Safety Checks

Use this sequence after creating or migrating a module:

```bash
php artisan module:validate
php artisan permission:sync
php artisan module:list
```
