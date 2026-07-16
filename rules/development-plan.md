# Admin Panel Boilerplate — Complete Development Plan

**Version:** 2.0 (Revised — Simple Panel Architecture)
**Date:** 2026-02-08

---

## Architecture Overview

Based on `rules/simple-panel-system.md`, the system cleanly separates **Panels** (presentation) from **Modules** (domain logic):

```
config/panels.php            ← Master switch: register/unregister panels (one line each)
app/Panels/{PanelName}/      ← Presentation: Controllers, Middleware, Requests, routes.php
app/Modules/{ModuleName}/    ← Domain Logic: Models, Services, Actions, Repos, Migrations
app/Modules/Shared/          ← Cross-module shared code: Traits, Helpers
resources/views/panels/      ← Panel-specific views (namespaced: view('admin::dashboard'))
resources/views/components/  ← Shared Blade components (used by all panels)
```

**Core Principles:**
- Panels are **presentation only** — they consume modules
- Modules are **business logic only** — they know nothing about panels
- Add panel: `php artisan make:panel Vendor` + 1 config line
- Remove panel: Delete folder + remove config line
- Add module: `php artisan make:module Blog --panels=admin,user`
- Remove module: Delete folder
- No unnecessary base classes — use Laravel's built-ins + simple traits

**External Packages (only 2):**
1. `spatie/laravel-permission` (composer) — RBAC
2. `alpinejs` (npm) — Client interactivity

---

## Current State

| Component | Status |
|-----------|--------|
| Laravel 12, PHP 8.2+, Vite 7, Tailwind CSS 4, Pest 3.8 | Installed |
| `app/Services/Table/TableConfig.php` | Working (search, sort, cursor pagination) |
| `app/Models/User.php` | Standard Eloquent model |
| `app/Panels/`, `app/Modules/` | Not created yet |
| Auth, middleware, RBAC | Not implemented |
| Views (beyond welcome) | Not created |
| Alpine.js | Not installed |
| Spatie Permission | Not installed |

---

## Phase 1: Core Infrastructure

**Goal:** Wire up the panel/module auto-loading system so everything else plugs in.
**Depends on:** Nothing (foundation phase)
**Estimated files:** ~12

### Tasks

- [ ] **1.1 — Install Alpine.js**
  - `npm install alpinejs`
  - Update `resources/js/app.js`:
    ```js
    import './bootstrap';
    import Alpine from 'alpinejs';
    window.Alpine = Alpine;
    Alpine.start();
    ```

- [ ] **1.2 — Create `config/panels.php`**
  - Panel registry with `admin` and `user` entries
  - Each entry: name, prefix, middleware, roles, guard, theme, active
  - Exactly as defined in `rules/simple-panel-system.md` Step 1

- [ ] **1.3 — Create `app/Providers/PanelServiceProvider.php`**
  - Loops `config('panels')`, skips inactive
  - Auto-loads each panel's `routes.php` with correct prefix/middleware/name
  - Registers panel views with namespace: `view('admin::dashboard')`
  - Binds `current.panel` singleton based on request URL segment
  - Code in `rules/simple-panel-system.md` Step 2

- [ ] **1.4 — Create `app/Providers/ModuleServiceProvider.php`**
  - Scans `app/Modules/*/Providers/*ServiceProvider.php`, registers each
  - Auto-loads `app/Modules/*/Database/Migrations/` for each module
  - Code in `rules/simple-panel-system.md` Step 3

- [ ] **1.5 — Create `app/Http/Middleware/PanelAccess.php`**
  - Checks panel config exists and is active
  - Checks user has required role (empty roles array = all authenticated users)
  - Shares `$panel` and `$panelConfig` to views
  - Code in `rules/simple-panel-system.md` Step 4

- [ ] **1.6 — Update `bootstrap/providers.php`**
  - Add `PanelServiceProvider::class` and `ModuleServiceProvider::class`
  - As shown in `rules/simple-panel-system.md` "Register Providers" section

- [ ] **1.7 — Update `bootstrap/app.php`**
  - Register middleware aliases: `panel`, `role`, `permission`
  - Keep existing routing for web.php, commands, health
  - ```php
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'panel' => \App\Http\Middleware\PanelAccess::class,
            'role'  => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    })
    ```

- [ ] **1.8 — Update `composer.json` PSR-4 autoload**
  - Add `"App\\Modules\\": "app/Modules/"` and `"App\\Panels\\": "app/Panels/"`
  - Run `composer dump-autoload`

- [ ] **1.9 — Create shared traits**
  - `app/Modules/Shared/Traits/HasCreatedUpdatedBy.php`
    - Auto-fills `created_by` / `updated_by` from `Auth::id()` on create/update
  - `app/Modules/Shared/Traits/HasAuditLog.php`
    - Hooks into model events (created, updated, deleted)
    - Calls `AuditLogService::log()` (service created in Phase 2)
  - `app/Modules/Shared/Traits/ApiResponse.php`
    - `successResponse($data, $message, $code)` and `errorResponse($message, $code, $errors)` helpers

### Files Summary

| File | Action |
|------|--------|
| `config/panels.php` | NEW |
| `app/Providers/PanelServiceProvider.php` | NEW |
| `app/Providers/ModuleServiceProvider.php` | NEW |
| `app/Http/Middleware/PanelAccess.php` | NEW |
| `app/Modules/Shared/Traits/HasCreatedUpdatedBy.php` | NEW |
| `app/Modules/Shared/Traits/HasAuditLog.php` | NEW |
| `app/Modules/Shared/Traits/ApiResponse.php` | NEW |
| `bootstrap/providers.php` | MODIFY |
| `bootstrap/app.php` | MODIFY |
| `composer.json` | MODIFY |
| `resources/js/app.js` | MODIFY |
| `package.json` | MODIFY (npm install) |

### Verify
- `composer dump-autoload` — no errors
- `php artisan serve` — app loads without errors
- Namespaces resolve correctly

---

## Phase 2: Authentication & RBAC

**Goal:** Custom auth controllers, Spatie roles/permissions, audit logging, user seeder.
**Depends on:** Phase 1
**Estimated files:** ~25

### Tasks

- [ ] **2.1 — Install Spatie Permission**
  - `composer require spatie/laravel-permission`
  - `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`

- [ ] **2.2 — Create migration: `add_fields_to_users_table`**
  - Add columns: `is_active` (boolean, default true), `last_login_at` (timestamp nullable), `last_login_ip` (string 45 nullable), `avatar` (string nullable), `phone` (string 20 nullable)
  - Add `softDeletes()`

- [ ] **2.3 — Create AuditLog module**
  ```
  app/Modules/AuditLog/
    Models/AuditLog.php
    Services/AuditLogService.php
    Database/Migrations/create_audit_logs_table.php
    Providers/AuditLogServiceProvider.php
  ```
  - **AuditLog model:** read-only, casts old_values/new_values as array, `user()` belongsTo, `auditable()` morphTo
  - **AuditLogService:**
    - `log(Model $model, string $action)` — records old/new values diff, excludes password/remember_token
    - `logCustom(string $action, ?array $metadata)` — for non-model events (login, logout, etc.)
  - **Migration columns:** id, user_id (FK nullable), action (string 50), auditable_type, auditable_id (nullable), old_values (json), new_values (json), ip_address (string 45), user_agent (text), url (text), timestamps
  - **Indexes:** (auditable_type, auditable_id), user_id, action, created_at
  - No controllers yet — admin panel controllers come in Phase 4

- [ ] **2.4 — Update `app/Models/User.php`**
  - Add `Spatie\Permission\Traits\HasRoles` trait
  - Add `SoftDeletes` trait
  - Update `$fillable` with new fields: is_active, last_login_at, last_login_ip, avatar, phone
  - Update `casts()` with: is_active → boolean, last_login_at → datetime
  - Add helpers: `isAdmin()`, `isStaff()`

- [ ] **2.5 — Create Auth Controllers** (`app/Http/Controllers/Auth/`)
  | Controller | Handles |
  |------------|---------|
  | `LoginController` | GET show form, POST authenticate, record last_login_at + ip, redirect by role (admin→/admin, user→/dashboard), audit log |
  | `RegisterController` | GET show form, POST create user + assign 'user' role |
  | `ForgotPasswordController` | GET show form, POST send reset link |
  | `ResetPasswordController` | GET show form with token, POST reset password |
  | `LogoutController` | POST invalidate session, audit log entry |

- [ ] **2.6 — Create Auth Form Requests** (`app/Http/Requests/Auth/`)
  - `LoginRequest` — email (required, email), password (required)
  - `RegisterRequest` — name (required), email (required, email, unique), password (required, confirmed, min:8)

- [ ] **2.7 — Add auth routes to `routes/web.php`**
  ```php
  // Guest routes
  Route::middleware('guest')->group(function () {
      Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
      Route::post('login', [LoginController::class, 'login']);
      Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
      Route::post('register', [RegisterController::class, 'register']);
      Route::get('forgot-password', [ForgotPasswordController::class, 'showForm'])->name('password.request');
      Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->name('password.email');
      Route::get('reset-password/{token}', [ResetPasswordController::class, 'showForm'])->name('password.reset');
      Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
  });

  // Authenticated routes
  Route::middleware('auth')->group(function () {
      Route::post('logout', [LogoutController::class, 'logout'])->name('logout');
  });
  ```

- [ ] **2.8 — Create auth views** (minimal styling — design comes later)
  - `resources/views/layouts/guest.blade.php` — Simple centered card layout with Tailwind
  - `resources/views/auth/login.blade.php`
  - `resources/views/auth/register.blade.php`
  - `resources/views/auth/forgot-password.blade.php`
  - `resources/views/auth/reset-password.blade.php`

- [ ] **2.9 — Create `database/seeders/RolePermissionSeeder.php`**
  - Roles: `super-admin`, `admin`, `staff`, `user`
  - Permissions (grouped by module):
    - Users: `users.view`, `users.create`, `users.edit`, `users.delete`
    - Roles: `roles.view`, `roles.create`, `roles.edit`, `roles.delete`
    - Audit: `audit-logs.view`, `audit-logs.export`
    - Settings: `settings.view`, `settings.edit`
    - Dashboard: `dashboard.view`
  - `super-admin` — bypasses all checks via Gate::before
  - `admin` — gets all permissions
  - `staff` — gets view-only: users.view, audit-logs.view, dashboard.view
  - `user` — no admin permissions (user panel only)

- [ ] **2.10 — Create `database/seeders/AdminUserSeeder.php`**
  - Super admin: admin@admin.com / password (role: super-admin)
  - Staff user: staff@staff.com / password (role: staff)
  - Regular user: user@user.com / password (role: user)

- [ ] **2.11 — Update `database/seeders/DatabaseSeeder.php`**
  - Call `RolePermissionSeeder` first, then `AdminUserSeeder`

- [ ] **2.12 — Add Gate::before in `AppServiceProvider`**
  ```php
  public function boot(): void
  {
      Gate::before(fn($user, $ability) => $user->hasRole('super-admin') ? true : null);
  }
  ```

### Files Summary

| File | Action |
|------|--------|
| `app/Modules/AuditLog/Models/AuditLog.php` | NEW |
| `app/Modules/AuditLog/Services/AuditLogService.php` | NEW |
| `app/Modules/AuditLog/Database/Migrations/create_audit_logs_table.php` | NEW |
| `app/Modules/AuditLog/Providers/AuditLogServiceProvider.php` | NEW |
| `database/migrations/xxxx_add_fields_to_users_table.php` | NEW |
| `app/Http/Controllers/Auth/LoginController.php` | NEW |
| `app/Http/Controllers/Auth/RegisterController.php` | NEW |
| `app/Http/Controllers/Auth/ForgotPasswordController.php` | NEW |
| `app/Http/Controllers/Auth/ResetPasswordController.php` | NEW |
| `app/Http/Controllers/Auth/LogoutController.php` | NEW |
| `app/Http/Requests/Auth/LoginRequest.php` | NEW |
| `app/Http/Requests/Auth/RegisterRequest.php` | NEW |
| `resources/views/layouts/guest.blade.php` | NEW |
| `resources/views/auth/login.blade.php` | NEW |
| `resources/views/auth/register.blade.php` | NEW |
| `resources/views/auth/forgot-password.blade.php` | NEW |
| `resources/views/auth/reset-password.blade.php` | NEW |
| `database/seeders/RolePermissionSeeder.php` | NEW |
| `database/seeders/AdminUserSeeder.php` | NEW |
| `app/Models/User.php` | MODIFY |
| `app/Providers/AppServiceProvider.php` | MODIFY |
| `database/seeders/DatabaseSeeder.php` | MODIFY |
| `routes/web.php` | MODIFY |

### Verify
- `php artisan migrate` — runs clean
- `php artisan db:seed` — creates roles, permissions, users
- Visit /login → form loads
- Login as admin@admin.com → redirects to /admin (404 for now — panel not built yet)
- Login as user@user.com → redirects to /dashboard (404 for now)
- Logout works, session invalidated

---

## Phase 3: CLI Commands & Stubs

**Goal:** `make:panel`, `make:module`, and supporting commands to scaffold everything quickly.
**Depends on:** Phase 1
**Estimated files:** ~25

### Tasks

- [ ] **3.1 — Create `app/Console/Commands/MakePanelCommand.php`**
  - Signature: `make:panel {name}`
  - Creates: `app/Panels/{Name}/Controllers/`, `Middleware/`, `Requests/`
  - Creates: `app/Panels/{Name}/routes.php` with dashboard route
  - Creates: `app/Panels/{Name}/Controllers/DashboardController.php`
  - Creates: `app/Panels/{Name}/Middleware/{Name}Access.php`
  - Creates: `resources/views/panels/{key}/layouts/app.blade.php`
  - Creates: `resources/views/panels/{key}/dashboard.blade.php`
  - Prints config snippet to add to `config/panels.php`
  - Based on `rules/simple-panel-system.md` Step 7

- [ ] **3.2 — Create `app/Console/Commands/MakeModuleCommand.php`**
  - Signature: `make:module {name} {--panels=admin}`
  - Creates: `app/Modules/{Name}/Models/`, `Services/`, `Actions/`, `Repositories/Interfaces/`, `Policies/`, `Events/`, `Database/Migrations/`, `Database/Seeders/`, `Providers/`
  - Creates: `{Name}ServiceProvider.php` (auto-discovered)
  - For each specified panel: Creates `app/Panels/{Panel}/Controllers/{Name}Controller.php`
  - Creates: view stubs under `resources/views/panels/{panel}/{module-kebab}/`
  - Based on `rules/simple-panel-system.md` Step 8

- [ ] **3.3 — Create stub templates** (`stubs/`)
  ```
  stubs/
    panel/
      routes.stub                    ← Panel routes file
      DashboardController.stub       ← Panel dashboard controller
      PanelAccess.stub               ← Panel-specific middleware
      layout.blade.stub              ← Panel layout template
      dashboard.blade.stub           ← Panel dashboard view
    module/
      Model.stub                     ← Eloquent model
      Service.stub                   ← Service class
      Action.stub                    ← Action class
      Repository.stub                ← Repository class
      RepositoryInterface.stub       ← Repository interface
      Policy.stub                    ← Authorization policy
      Migration.stub                 ← Migration template
      Seeder.stub                    ← Seeder template
      ServiceProvider.stub           ← Module service provider
      PanelController.stub           ← Controller for a panel
      views/
        index.blade.stub             ← List view
        create.blade.stub            ← Create form
        edit.blade.stub              ← Edit form
        show.blade.stub              ← Detail view
        partials/table-rows.blade.stub  ← AJAX table rows partial
      tests/
        Feature.stub                 ← Feature test template
        Unit.stub                    ← Unit test template
  ```

- [ ] **3.4 — Create `app/Console/Commands/RemovePanelCommand.php`**
  - Signature: `remove:panel {name} {--confirm}`
  - Deletes `app/Panels/{Name}/` and `resources/views/panels/{key}/`
  - Warns: "Remove the entry from config/panels.php manually"
  - Requires `--confirm` flag to actually delete

- [ ] **3.5 — Create `app/Console/Commands/RemoveModuleCommand.php`**
  - Signature: `remove:module {name} {--confirm}`
  - Deletes `app/Modules/{Name}/`
  - Warns: "Check for related controllers in app/Panels/*/Controllers/"
  - Warns: "Run php artisan migrate:rollback if needed"
  - Requires `--confirm` flag

- [ ] **3.6 — Create `app/Console/Commands/ListPanelsCommand.php`**
  - Signature: `panels:list`
  - Lists all panels from config with: name, prefix, roles, active status

- [ ] **3.7 — Create `app/Console/Commands/ListModulesCommand.php`**
  - Signature: `modules:list`
  - Scans `app/Modules/`, lists: name, has service provider, migration count

### Files Summary

| File | Action |
|------|--------|
| `app/Console/Commands/MakePanelCommand.php` | NEW |
| `app/Console/Commands/MakeModuleCommand.php` | NEW |
| `app/Console/Commands/RemovePanelCommand.php` | NEW |
| `app/Console/Commands/RemoveModuleCommand.php` | NEW |
| `app/Console/Commands/ListPanelsCommand.php` | NEW |
| `app/Console/Commands/ListModulesCommand.php` | NEW |
| `stubs/panel/*.stub` (5 files) | NEW |
| `stubs/module/*.stub` (10 files) | NEW |
| `stubs/module/views/*.blade.stub` (5 files) | NEW |
| `stubs/module/tests/*.stub` (2 files) | NEW |

### Verify
- `php artisan make:panel Test` → creates correct folder structure
- `php artisan make:module TestModule --panels=admin` → creates module + admin controller
- `php artisan panels:list` → shows admin, user panels
- `php artisan modules:list` → shows TestModule
- `php artisan remove:module TestModule --confirm` → cleans up
- `php artisan remove:panel Test --confirm` → cleans up

---

## Phase 4: Admin Panel + Core Modules

**Goal:** Scaffold Admin and User panels, build core modules (UserManagement, Settings).
**Depends on:** Phases 1, 2, 3
**Estimated files:** ~40

### Tasks

- [ ] **4.1 — Create Admin Panel** (using `make:panel Admin` then customize)
  ```
  app/Panels/Admin/
    Controllers/
      DashboardController.php      ← Widget data (total users, recent activity, system info)
      UserController.php           ← Full CRUD using UserManagement module
      RoleController.php           ← CRUD using Spatie Role model directly
      AuditLogController.php       ← Read-only list + detail view
      SettingController.php        ← Grouped settings form (tabs by group)
    Middleware/
      AdminAccess.php              ← Panel-specific middleware
    Requests/
      StoreUserRequest.php         ← name, email, password, role validation
      UpdateUserRequest.php        ← name, email, role (password optional)
      StoreRoleRequest.php         ← name, permissions[]
    routes.php                     ← All admin routes (resourceful + custom)
  ```

- [ ] **4.2 — Create User Panel** (using `make:panel User` then customize)
  ```
  app/Panels/User/
    Controllers/
      DashboardController.php      ← Simple user dashboard
      ProfileController.php        ← Edit own profile (name, email, password, avatar)
    Middleware/
      UserAccess.php
    Requests/
      UpdateProfileRequest.php
    routes.php
  ```

- [ ] **4.3 — Create UserManagement Module**
  ```
  app/Modules/UserManagement/
    Services/UserService.php       ← listPaginated(), findOrFail(), create(), update(),
                                      delete(), toggleStatus(), count(), recent()
    Actions/CreateUserAction.php   ← Create user + assign role + audit log
    Actions/UpdateUserAction.php   ← Update user + sync roles + audit log
    Repositories/UserRepository.php ← Eloquent queries (search, filter by role/status, sort)
    Policies/UserPolicy.php        ← viewAny, view, create, update, delete (checks Spatie permissions)
    Database/Seeders/UserSeeder.php ← 20 sample users with mixed roles
    Providers/UserManagementServiceProvider.php ← Binds repository interface
  ```
  **Note:** Uses existing `app/Models/User.php` — no new model.

- [ ] **4.4 — Create Settings Module**
  ```
  app/Modules/Settings/
    Models/Setting.php             ← group, key, value, type, label, description, is_public
    Services/SettingsService.php   ← get(), set(), getGroup(), all() + Redis/database cache
    Database/Migrations/create_settings_table.php
    Database/Seeders/SettingSeeder.php ← Default settings (site_name, site_description, etc.)
    Providers/SettingsServiceProvider.php ← Registers global setting() helper
  ```
  **Settings table columns:** id, group (string 50, default 'general'), key (string 100, unique), value (text nullable), type (string 20, default 'string'), label (string), description (text nullable), is_public (boolean, default false), timestamps

- [ ] **4.5 — Create Dashboard Service**
  - `app/Modules/Shared/Services/DashboardService.php`
  - Methods: `getWidgetData()` returns array with:
    - Total users count (with growth % vs last month)
    - Recent audit log entries (last 10)
    - Role distribution stats
    - System info (PHP version, Laravel version, DB size)
  - Cache widget data for 5 minutes

- [ ] **4.6 — Create Admin Panel Views**
  ```
  resources/views/panels/admin/
    layouts/app.blade.php          ← Sidebar + header + content area
    dashboard.blade.php            ← Widget cards
    users/
      index.blade.php              ← Table with search, sort, pagination
      create.blade.php             ← Form with role dropdown
      edit.blade.php               ← Form with role dropdown
      show.blade.php               ← User detail + audit history
    roles/
      index.blade.php              ← Table of roles with permission count
      create.blade.php             ← Form with permission checkboxes
      edit.blade.php               ← Form with permission checkboxes
    audit-logs/
      index.blade.php              ← Filterable list (user, action, model, date)
      show.blade.php               ← Detail with old/new values diff
    settings/
      index.blade.php              ← Grouped settings form (tabs)
  ```

- [ ] **4.7 — Create User Panel Views**
  ```
  resources/views/panels/user/
    layouts/app.blade.php          ← User panel layout
    dashboard.blade.php
    profile/
      edit.blade.php               ← Edit own name, email, password, avatar
  ```

- [ ] **4.8 — Update `config/panels.php`**
  - Ensure admin panel has: prefix `admin`, roles `['super-admin', 'admin']`
  - Ensure user panel has: prefix `dashboard`, roles `[]` (all authenticated)

- [ ] **4.9 — Admin routes** (`app/Panels/Admin/routes.php`)
  ```php
  Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
  Route::resource('users', UserController::class);
  Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
  Route::resource('roles', RoleController::class);
  Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
  Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
  Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
  Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
  ```

### Verify
- Login as admin → /admin dashboard shows widget data
- /admin/users → List, create, edit, delete, toggle status all work
- /admin/roles → Create/edit roles with permission checkboxes
- /admin/audit-logs → Shows logged model changes and auth events
- /admin/settings → Edit settings, verify cache works (`setting('site_name')`)
- Login as user → /dashboard loads, /dashboard/profile works
- Admin CANNOT access /dashboard, user CANNOT access /admin

---

## Phase 5: Blade Components + Table System

**Goal:** Shared reusable components for all panels + enhanced table builder.
**Depends on:** Phase 4 (so components can be tested against real views)
**Estimated files:** ~20

### Tasks

- [ ] **5.1 — Enhance Table System**
  - `app/Services/Table/Column.php` — Value object: field, label, sortable, searchable, format
  - `app/Services/Table/TableBuilder.php` — Fluent API:
    ```php
    TableBuilder::for(User::query())
        ->searchable(['name', 'email'])
        ->sortable(['name', 'email', 'created_at'])
        ->defaultSort('created_at', 'desc')
        ->perPage(25)
        ->build();
    ```
  - Returns data + column config + current sort/search state
  - Keep existing `TableConfig.php` as a simpler alternative

- [ ] **5.2 — Create Table Components** (`resources/views/components/table/`)
  - `wrapper.blade.php` — Alpine.js wrapper that fetches table-rows partial on search/sort/page change
  - `header.blade.php` — Sortable column headers (click to toggle sort)
  - `pagination.blade.php` — Cursor or offset pagination links
  - `search.blade.php` — Search input with Alpine.js debounce (300ms)

- [ ] **5.3 — Create Form Components** (`resources/views/components/form/`)
  - `input.blade.php` — Text/email/password with label, error display, old() value
  - `select.blade.php` — Dropdown with options array, optional placeholder
  - `textarea.blade.php` — Textarea with label and error
  - `checkbox.blade.php` — Single checkbox with label
  - `toggle.blade.php` — On/off switch (Alpine.js)
  - `submit.blade.php` — Submit button with loading state

- [ ] **5.4 — Create UI Components** (`resources/views/components/ui/`)
  - `modal.blade.php` — Alpine.js modal with slot for content
  - `alert.blade.php` — Success/error/warning/info alert box
  - `badge.blade.php` — Status badges with color variants
  - `button.blade.php` — Button with variants: primary, secondary, danger, outline
  - `card.blade.php` — Card wrapper with optional header slot
  - `confirm.blade.php` — Delete confirmation dialog (Alpine.js)
  - `flash.blade.php` — Auto-dismiss flash messages from session

- [ ] **5.5 — Create Navigation Components** (`resources/views/components/navigation/`)
  - `sidebar.blade.php` — Collapsible sidebar, items filtered by user permissions
  - `sidebar-item.blade.php` — Single nav item with active state detection
  - `header.blade.php` — Top bar with user dropdown (name, role, logout)
  - `breadcrumb.blade.php` — Dynamic breadcrumbs

- [ ] **5.6 — Update panel layouts** to use shared components
  - Admin layout uses `<x-navigation.sidebar>`, `<x-navigation.header>`, `<x-ui.flash>`
  - User layout uses simplified sidebar + header

- [ ] **5.7 — Update Phase 4 views** to use component syntax
  - Replace raw HTML tables with `<x-table.wrapper>`
  - Replace raw form inputs with `<x-form.input>`, `<x-form.select>`, etc.
  - Replace inline alerts with `<x-ui.alert>`

### Verify
- Tables render with working search, sort, pagination
- AJAX partial loading works (table body updates without full page reload)
- Forms show validation errors correctly with `<x-form.input>`
- Modals open/close, confirmations prevent accidental deletes
- Flash messages show and auto-dismiss after 5 seconds
- Sidebar highlights active route, hides items user lacks permissions for

---

## Phase 6: API + Testing

**Goal:** Sanctum API endpoints + comprehensive Pest test suite + CI pipeline.
**Depends on:** Phases 1–5
**Estimated files:** ~20

### Tasks

- [ ] **6.1 — Create API Auth Controller**
  - `app/Http/Controllers/Api/AuthController.php`
  - `login(Request)` — validate email/password, issue Sanctum token
  - `logout(Request)` — revoke current token
  - `me(Request)` — return authenticated user with roles

- [ ] **6.2 — Create API Resources**
  - `app/Http/Resources/UserResource.php` — id, name, email, is_active, roles, timestamps
  - `app/Http/Resources/RoleResource.php` — id, name, permissions
  - `app/Http/Resources/SettingResource.php` — key, value, type, group (public only)

- [ ] **6.3 — Create API routes** (`routes/api.php`)
  ```php
  Route::prefix('v1')->name('api.v1.')->group(function () {
      Route::post('login', [AuthController::class, 'login'])->name('login');
      Route::middleware('auth:sanctum')->group(function () {
          Route::post('logout', [AuthController::class, 'logout'])->name('logout');
          Route::get('me', [AuthController::class, 'me'])->name('me');
      });
  });
  ```

- [ ] **6.4 — Create Test Helper** (`tests/Helpers/TestHelper.php`)
  ```php
  trait TestHelper {
      protected function createAdmin(): User { /* create + assign super-admin */ }
      protected function createStaff(): User { /* create + assign staff */ }
      protected function createUser(): User { /* create + assign user */ }
      protected function seedPermissions(): void { /* call RolePermissionSeeder */ }
  }
  ```

- [ ] **6.5 — Write Auth Tests** (`tests/Feature/Auth/`)
  - `LoginTest.php` — page loads, valid login, invalid credentials, inactive user blocked, role-based redirect, last_login recorded
  - `RegisterTest.php` — page loads, valid registration, user role assigned, validation errors
  - `LogoutTest.php` — session invalidated, redirected to login

- [ ] **6.6 — Write Panel Access Tests** (`tests/Feature/Panels/`)
  - `AdminAccessTest.php` — admin can access /admin, user gets 403, unauthenticated gets redirected
  - `UserAccessTest.php` — user can access /dashboard, unauthenticated gets redirected

- [ ] **6.7 — Write Admin CRUD Tests** (`tests/Feature/Admin/`)
  - `UserCrudTest.php` — index, create, store, edit, update, destroy, toggle status, permission checks
  - `RoleCrudTest.php` — index, create, store, edit, update, destroy, permission assignment
  - `AuditLogTest.php` — entries created on model changes, index shows entries, show shows diff
  - `SettingsTest.php` — get/set/cache settings, admin can update, staff cannot

- [ ] **6.8 — Write Unit Tests** (`tests/Unit/Services/`)
  - `UserServiceTest.php` — listPaginated, findOrFail, create, update, delete
  - `SettingsServiceTest.php` — get, set, getGroup, cache invalidation
  - `AuditLogServiceTest.php` — log model changes, logCustom, excluded fields
  - `TableBuilderTest.php` — search, sort, pagination, column config

- [ ] **6.9 — Write API Tests** (`tests/Feature/Api/`)
  - `AuthApiTest.php` — login returns token, me returns user, logout revokes token

- [ ] **6.10 — Create CI Pipeline** (`.github/workflows/ci.yml`)
  - Trigger: push to main/develop, PRs
  - PHP matrix: 8.2, 8.3
  - MySQL service
  - Steps: composer install, copy env, generate key, migrate, run Pest with coverage
  - Run Pint code style check

### Verify
- `php artisan test` — all green
- `php artisan test --coverage` — >= 80%
- API: POST /api/v1/login → token, GET /api/v1/me → user data, POST /api/v1/logout → success

---

## Phase 7: Documentation

**Goal:** Developer docs, guides, README, task checklist.
**Depends on:** All phases
**Estimated files:** ~12

### Tasks

- [ ] **7.1 — Architecture docs** (`rules/docs/architecture/`)
  - `overview.md` — Panel vs Module separation, request lifecycle, auto-discovery
  - `folder-structure.md` — Complete annotated directory tree
  - `naming-conventions.md` — Naming rules for controllers, models, views, routes, etc.

- [ ] **7.2 — Developer guides** (`rules/docs/guides/`)
  - `quick-start.md` — Clone → composer install → env → migrate → seed → npm install → npm run dev
  - `panel-guide.md` — make:panel usage, customization, removal
  - `module-guide.md` — make:module usage, customization, removal
  - `component-guide.md` — Blade component reference with examples
  - `api-guide.md` — API auth, versioning, resources, response format

- [ ] **7.3 — Update `README.md`**
  - Project name, description
  - Requirements (PHP 8.2+, MySQL, Node 18+)
  - 5-command quick start
  - Architecture overview diagram
  - Available CLI commands
  - Links to detailed docs

- [ ] **7.4 — Create `CONTRIBUTING.md`**
  - Code style: PSR-12 via Laravel Pint
  - Commit message format
  - PR checklist
  - Branch naming convention

- [ ] **7.5 — Create phase checklist** (`rules/docs/tasks/phase-checklist.md`)
  - All tasks from all phases with completion status

---

## Implementation Order

```
Phase 1 (Infrastructure)   ~12 files   ← Foundation: providers, middleware, config
       ↓
Phase 2 (Auth + RBAC)      ~25 files   ← Auth controllers, Spatie, seeders, audit
       ↓
Phase 3 (CLI Commands)     ~25 files   ← make:panel, make:module, stubs
       ↓
Phase 4 (Panels + Modules) ~40 files   ← Admin panel, User panel, core modules
       ↓
Phase 5 (Components)       ~20 files   ← Blade components, table system
       ↓
Phase 6 (API + Testing)    ~20 files   ← Sanctum API, Pest tests, CI
       ↓
Phase 7 (Documentation)    ~12 files   ← Docs, README, guides
```

**Total: ~154 files, 7 phases**

---

## What Was Intentionally NOT Included (and Why)

| Omitted | Reason |
|---------|--------|
| BaseController, BaseModel, BaseService, BaseAction | Over-engineering. Laravel's built-ins are sufficient. Use simple traits where needed. |
| BaseRepository / RepositoryInterface | Services use Eloquent directly. Repositories only where query logic warrants it. |
| config/modules.php | ModuleServiceProvider auto-discovers — no config needed. |
| config/theme.php | Panel config already has a `theme` key. Full theme system is premature. |
| config/audit.php | 3 constants don't need their own config file. |
| ViewComposerServiceProvider | PanelServiceProvider already shares panel context. |
| nwidart/laravel-modules | Custom system is simpler and fully under our control. |
| Laravel Fortify/Breeze | Custom auth gives full control with zero extra dependencies. |
| Livewire | Not needed — Alpine.js + AJAX partials is lighter and sufficient. |
| Multi-tenancy | Out of scope per PRD. Can be added later as extension. |

---

## Final Verification Checklist

After all phases are complete:

- [ ] `php artisan make:panel Vendor` → creates working panel scaffold
- [ ] Add 1 config line → visit /vendor → panel loads
- [ ] `php artisan make:module Blog --panels=admin,user` → creates module + controllers
- [ ] Login as admin → /admin dashboard, users CRUD, roles CRUD, audit logs, settings
- [ ] Login as user → /dashboard, profile edit only
- [ ] Login as staff → /admin with limited permissions (view only)
- [ ] Admin CANNOT access /dashboard, user CANNOT access /admin
- [ ] `setting('site_name')` → returns cached value from database
- [ ] Model changes appear in audit log automatically
- [ ] `php artisan remove:panel Vendor --confirm` → clean removal
- [ ] `php artisan remove:module Blog --confirm` → clean removal
- [ ] `php artisan test` → all green
- [ ] `php artisan test --coverage` → >= 80%
- [ ] `vendor/bin/pint --test` → code style passes
