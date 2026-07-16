# Module Runtime

Modern modules are self-contained and live under `app/Modules/{Name}`.

## Quick Start

Create a CRUD module:

```bash
php artisan make:module Demo --panels=admin
```

Validate and sync:

```bash
php artisan module:validate Demo
php artisan permission:sync
php artisan module:list
```

## Runtime Files

- `module.json`: static metadata and optional package declarations
- `Module.php`: descriptor for permissions, policies, navigation
- `storage/app/module-state.json`: enable/disable overrides
- `bootstrap/cache/modules.php`: cached module registry

## Core Commands

- `php artisan module:list`
- `php artisan module:cache`
- `php artisan module:cache:clear`
- `php artisan module:enable {Module}`
- `php artisan module:disable {Module}`
- `php artisan module:validate {Module?}`
- `php artisan permission:sync` (`module:sync-permissions` alias)

## Package Sync (Root Composer Strategy)

This monolith uses one root `composer.json`.

Module declarations can contribute package requirements through `module.json`:

```json
{
  "packages": {
    "require": {
      "stripe/stripe-php": "^20.0"
    }
  }
}
```

Sync declarations into root composer:

```bash
php artisan module:sync-packages --write
```

Check only:

```bash
php artisan module:sync-packages --check
```

## Tables Default

New CRUD modules scaffold with:

- `Tables/{Module}Table.php`
- controller `tableDefinition(Request $request): ?TableDefinition`
- index view rendering with `<x-tables.resource ... />`

Legacy `_table-rows` is optional fallback.

## Canonical Docs

- Module authoring/runtime: [docs/modules.md](/D:/xampp/htdocs/boilerplate/admin-panel/docs/modules.md)
- Schema tables: [docs/datatable.md](/D:/xampp/htdocs/boilerplate/admin-panel/docs/datatable.md)
- High-level onboarding: [docs/developer-guide.md](/D:/xampp/htdocs/boilerplate/admin-panel/docs/developer-guide.md)
