# Developer Guide

High-level onboarding for this codebase. Use the linked docs for canonical details.

## Start Here

Install and bootstrap:

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan permission:sync
npm install
npm run build
```

Run locally:

```bash
composer dev
```

## Architecture at a Glance

- Panels are shell layers (`admin`, `user`) for auth/dashboard/profile/2FA and shell chrome.
- Modules are feature layers under `app/Modules/*`.
- Runtime discovery/state/caching is module-driven via `ModuleRegistry`.
- Permissions are synced from module descriptors into Spatie with `permission:sync`.
- Table pages default to schema-driven rendering with `TableDefinition` and `<x-tables.resource>`.

## Project Layout (Modern)

```text
app/
  Modules/
    {Feature}/
      module.json
      Module.php
      Http/Controllers/{Panel}/...
      Routes/{panel}.php
      Resources/views/{panel}/...
      Resources/lang/...
      Tables/...
      Services/...
      Tests/...
    Shared/
      Support/...
      Traits/...
  Panels/
    Admin/   (shell-only routes/controllers)
    User/    (shell-only routes/controllers)
docs/
  modules.md
  datatable.md
```

## Daily Workflow

Create module:

```bash
php artisan make:module Invoice --panels=admin
```

Validate and sync:

```bash
php artisan module:validate
php artisan permission:sync
php artisan module:list
```

If module package declarations changed:

```bash
php artisan module:sync-packages --write
```

## Canonical Docs

- Module runtime, contracts, generator, lifecycle commands: [modules.md](/D:/xampp/htdocs/boilerplate/admin-panel/docs/modules.md)
- Schema table system and table APIs: [datatable.md](/D:/xampp/htdocs/boilerplate/admin-panel/docs/datatable.md)
- Blade component reference: [components.md](/D:/xampp/htdocs/boilerplate/admin-panel/docs/components.md)
- Quick module ops summary: [app/Modules/README.md](/D:/xampp/htdocs/boilerplate/admin-panel/app/Modules/README.md)

## Quick Rules

- Keep feature routes/controllers/views/tests inside modules.
- Keep panel files shell-only.
- Use kebab route names for custom actions (`toggle-status`, `bulk-toggle-status`).
- Prefer schema tables for new CRUD screens.
- Root `composer.json` is the only active Composer manifest in this monolith.
