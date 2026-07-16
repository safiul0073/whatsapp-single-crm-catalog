# Admin Panel Boilerplate

A production-ready Laravel admin panel boilerplate with modular architecture, multi-guard authentication, role-based permissions, multi-language support, and a flexible component system.

## Features

- **Multi-Guard Authentication** -- Separate `admins` and `users` tables with independent login pages (`/admin/login` and `/login`)
- **Role-Based Permissions** -- Spatie Laravel Permission with dot-notation permissions (`users.view`, `products.create`, etc.)
- **Panel System** -- Create isolated admin panels with their own controllers, routes, views, and middleware
- **Module System** -- Reusable business logic modules (Controller → Service → Model pattern)
- **Multi-Language (i18n)** -- File-based JSON translations (`resources/lang/{locale}.json`) with admin UI editor
- **Component Library** -- Pre-built Blade components (forms, tables, UI elements, navigation)
- **Panel Theming** -- Directory-based component override system for per-panel custom designs
- **Artisan Commands** -- `make:module` and `make:panel` for rapid scaffolding with full CRUD

## Tech Stack

- Laravel 12
- Blade + Alpine.js
- Tailwind CSS 4
- Phosphor Icons
- Spatie Laravel Permission

## Quick Start

```bash
# Clone and install
git clone <repo-url> admin-panel
cd admin-panel
composer install
npm install

# Configure
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed

# Build assets
npm run dev

# Start server
php artisan serve
```

## Default Credentials

| Role        | Email              | Password | Login URL      |
| ----------- | ------------------ | -------- | -------------- |
| Super Admin | admin@softivus.com | password | `/admin/login` |
| User        | user@gmail.com     | password | `/login`       |

## Project Structure

```
app/
├── Console/Commands/       # make:module, make:panel, etc.
├── Http/
│   ├── Controllers/Auth/   # User auth (web guard)
│   └── Middleware/          # PanelAccess, SetLocale
├── Models/                 # Admin, User
├── Modules/                # Business logic modules
│   ├── Products/           # Model, Service, Policy, etc.
│   ├── Notifications/
│   ├── Languages/
│   └── Shared/
│   ├── Settings/
│   ├── AuditLog/
│   └── Shared/
├── Panels/                 # Panel controllers & routes
│   ├── Admin/
│   │   ├── Controllers/    # UserController, ProductsController, Auth/
│   │   ├── Requests/       # StoreUserRequest, etc.
│   │   └── routes.php
│   └── User/
├── Providers/              # PanelServiceProvider, ModuleServiceProvider
config/
├── panels.php              # Panel registry (navigation, guard, middleware)
resources/views/
├── components/             # Shared Blade components
│   ├── forms/              # Input, Select, Toggle, etc.
│   ├── tables/             # Table, Search, Heading
│   ├── ui/                 # Button, Badge, Toast, etc.
│   ├── navigation/         # Sidebar, Topbar
│   └── layouts/            # Admin, User, Guest layouts
├── panels/
│   ├── admin/              # Admin panel views
│   └── user/               # User panel views
resources/lang/             # Translation files (en.json, bn.json, ar.json)
stubs/                      # Code generation stubs
```

## Artisan Commands

```bash
# Create a new module with full CRUD for admin panel
php artisan make:module Invoice --panels=admin

# Create a new panel
php artisan make:panel Merchant

# Create a panel with custom component overrides
php artisan make:panel Merchant --custom-components

# List all modules
php artisan module:list

# List all panels
php artisan panel:list
```

## Documentation

- [Developer Guide](docs/developer-guide.md) -- Architecture, panels, modules, auth, i18n, theming
- [Component Reference](docs/components.md) -- All Blade components with props and examples
- [Module Guide](docs/modules.md) -- Module development patterns and CRUD flow

## License

MIT
