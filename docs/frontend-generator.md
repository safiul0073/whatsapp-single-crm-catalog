# Frontend Generator

The stack now exposes a setup command:

`php artisan make:frontend-stack --panel=admin --parent=Settings`

## Current state

The current command validates that the frontend stack artifacts are present and prints the setup flow. The frontend stack itself is implemented directly in this boilerplate with:

- `Frontend` module
- admin controllers and views
- theme registry
- menu slot registry
- section registry
- public rendering
- docs

## Intended future expansion

The generator should eventually scaffold:

- frontend module structure
- theme registry config
- menu slot registry config
- section registry config
- example themes
- admin routes and navigation
- permissions
- docs

## Immediate developer workflow

Today, use the implemented stack directly:

1. run migrations
2. seed the database
3. sync permissions
4. open:
   - `/admin/frontend-themes`
   - `/admin/frontend-menus`
   - `/admin/frontend-sections`
   - `/admin/frontend-pages`

## Future extension

When the command is implemented fully, it should stay idempotent and follow the same conventions as:

- `make:module`
- `make:panel`
- `permission:sync`
