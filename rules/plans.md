Claude’s Plan
Admin Panel Boilerplate - Revised Plan (Simple Panel System)
Context
Rebuild the plan around the architecture in simple-panel-system.md which cleanly separates Panels (presentation) from Modules (domain logic). This is faster to build, easier to maintain, and truly modular.

Previous plan was over-engineered. The new approach drops: BaseController, BaseModel, BaseRepository, RepositoryInterface, BaseService, BaseAction, Filterable trait, ViewComposerServiceProvider, complex config files, and ~60% of the boilerplate code.

Architecture (from simple-panel-system.md)

app/Panels/{PanelName}/     ← Presentation (Controllers, Middleware, Requests, routes.php)
app/Modules/{ModuleName}/   ← Domain Logic (Models, Services, Actions, Repositories, Database/)
config/panels.php           ← Single source of truth for all panels
Add panel: php artisan make:panel Vendor + 1 config line
Remove panel: Delete folder + remove config line
Add module: php artisan make:module Blog --panels=admin,user
Remove module: Delete folder
Panels are presentation. Modules are business logic. Panels consume modules.

What Already Exists
Fresh Laravel 12, PHP 8.2+, Vite 7, Tailwind CSS 4, Pest 3.8
TableConfig.php - Working search/sort/cursor pagination
User.php - Standard Eloquent User model
bootstrap/app.php - Minimal bootstrap
bootstrap/providers.php - Only AppServiceProvider
No panels, no modules, no middleware, no auth, no views (except welcome)
Packages to Install
spatie/laravel-permission (composer) - RBAC
alpinejs (npm) - Client interactivity
Phase 1: Core Infrastructure (~15 files)
Goal: Wire up the panel/module auto-loading system so everything else plugs in cleanly.

1.1 Install Alpine.js
npm install alpinejs
Update resources/js/app.js:

import './bootstrap';
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();
1.2 Create config/panels.php
Exactly as defined in simple-panel-system.md - registry for admin + user panels with prefix, middleware, roles, guard, theme, active flag.

1.3 Create PanelServiceProvider
File: app/Providers/PanelServiceProvider.php

Loops config('panels'), skips inactive
Auto-loads each panel's routes.php with correct prefix/middleware/name
Registers panel views with namespace (view('admin::dashboard'))
Binds current.panel singleton based on request URL segment
1.4 Create ModuleServiceProvider
File: app/Providers/ModuleServiceProvider.php

Scans app/Modules/*/Providers/*ServiceProvider.php, registers each
Auto-loads app/Modules/*/Database/Migrations/ for each module
1.5 Create PanelAccess Middleware
File: app/Http/Middleware/PanelAccess.php

Checks panel config exists and is active
Checks user has required role (empty roles = all authenticated)
Shares $panel and $panelConfig to views
1.6 Register Everything
Update bootstrap/providers.php: add PanelServiceProvider, ModuleServiceProvider
Update bootstrap/app.php: register panel middleware alias + Spatie aliases
Update composer.json PSR-4 autoload: add App\\Modules\\ and App\\Panels\\
1.7 Create Shared Traits
Small, useful traits only:

app/Modules/Shared/Traits/HasCreatedUpdatedBy.php - Auto-fills created_by/updated_by
app/Modules/Shared/Traits/HasAuditLog.php - Hooks into model events, calls AuditLogService
app/Modules/Shared/Traits/ApiResponse.php - JSON response helpers for API controllers
Files created/modified:

config/panels.php                              NEW
app/Providers/PanelServiceProvider.php         NEW
app/Providers/ModuleServiceProvider.php        NEW
app/Http/Middleware/PanelAccess.php            NEW
app/Modules/Shared/Traits/HasCreatedUpdatedBy.php  NEW
app/Modules/Shared/Traits/HasAuditLog.php     NEW
app/Modules/Shared/Traits/ApiResponse.php     NEW
bootstrap/providers.php                        MODIFY
bootstrap/app.php                              MODIFY
composer.json                                  MODIFY (PSR-4)
resources/js/app.js                            MODIFY
package.json                                   MODIFY (alpine)
Verify:
composer dump-autoload succeeds
php artisan serve loads without errors
Namespaces resolve correctly
Phase 2: Auth + RBAC (~25 files)
Goal: Login/register/logout, Spatie roles & permissions, audit logging, admin user seeder.

2.1 Install Spatie

composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
2.2 Migrations
add_fields_to_users_table - is_active (bool), last_login_at, last_login_ip, avatar, phone, soft_deletes
create_audit_logs_table - user_id, action, auditable_type, auditable_id, old_values (json), new_values (json), ip_address, user_agent, url, timestamps
2.3 Update User Model
app/Models/User.php - Add HasRoles trait, new fillable fields, isAdmin(), isStaff() helpers. Keep it in its current location (it's a framework model, not a module model).

2.4 AuditLog Module (lightweight)

app/Modules/AuditLog/
  Models/AuditLog.php              - Read-only model, json casts, user() relation
  Services/AuditLogService.php     - log(model, action), logCustom(action, metadata)
  Database/Migrations/             - create_audit_logs_table
  Providers/AuditLogServiceProvider.php
No controllers yet - those come in Phase 4 when we build the Admin panel.

2.5 Auth Controllers (custom, no Fortify)

app/Http/Controllers/Auth/
  LoginController.php              - Show form + POST, record last_login, redirect by role
  RegisterController.php           - Show form + POST, assign 'user' role
  ForgotPasswordController.php     - Show form + send reset link
  ResetPasswordController.php      - Show form + handle reset
  LogoutController.php             - Invalidate session, audit log
2.6 Auth Form Requests

app/Http/Requests/Auth/
  LoginRequest.php
  RegisterRequest.php
2.7 Auth Routes
Add to routes/web.php:

Guest: GET/POST login, GET/POST register, GET/POST forgot-password, GET/POST reset-password
Auth: POST logout, email verification routes
2.8 Auth Views (minimal styling - design comes later)

resources/views/auth/
  login.blade.php
  register.blade.php
  forgot-password.blade.php
  reset-password.blade.php
resources/views/layouts/
  guest.blade.php                  - Simple centered card layout
2.9 Seeders
database/seeders/RolePermissionSeeder.php:
Roles: super-admin, admin, staff, user
Permissions: users.view, users.create, users.edit, users.delete, roles.view, roles.create, roles.edit, roles.delete, audit-logs.view, settings.view, settings.edit, dashboard.view
database/seeders/AdminUserSeeder.php:
Creates super-admin (admin@admin.com / password)
Creates sample staff and user accounts
Update DatabaseSeeder.php to call both
2.10 Gate::before for Super Admin
In AppServiceProvider.php boot():


Gate::before(fn($user, $ability) => $user->hasRole('super-admin') ? true : null);
Verify:
php artisan migrate runs clean
php artisan db:seed creates roles + admin user
Login at /login redirects admin to /admin, user to /dashboard
Logout works, session invalidated
Phase 3: CLI Commands (~25 files)
Goal: make:panel, make:module, and supporting commands with stub templates.

3.1 make:panel Command
File: app/Console/Commands/MakePanelCommand.php
As defined in simple-panel-system.md:

Creates app/Panels/{Name}/ with Controllers/, Middleware/, Requests/
Creates routes.php with dashboard route
Creates DashboardController.php
Creates {Name}Access.php middleware
Creates resources/views/panels/{name}/layouts/app.blade.php
Creates resources/views/panels/{name}/dashboard.blade.php
Prints config snippet to add to config/panels.php
3.2 make:module Command
File: app/Console/Commands/MakeModuleCommand.php
As defined in simple-panel-system.md:

php artisan make:module Blog --panels=admin,user
Creates app/Modules/{Name}/ with Models/, Services/, Actions/, Repositories/Interfaces/, Policies/, Events/, Database/Migrations/, Database/Seeders/, Providers/
Creates {Name}ServiceProvider.php
Creates panel controllers in app/Panels/{Panel}/Controllers/{Name}Controller.php for each specified panel
Auto-discovered by ModuleServiceProvider
3.3 Stub Templates

stubs/
  panel/
    routes.stub
    DashboardController.stub
    PanelAccess.stub
    layout.blade.stub
    dashboard.blade.stub
  module/
    Model.stub
    Service.stub
    Action.stub
    Repository.stub
    RepositoryInterface.stub
    Policy.stub
    Migration.stub
    Seeder.stub
    ServiceProvider.stub
    PanelController.stub
    views/
      index.blade.stub
      create.blade.stub
      edit.blade.stub
      show.blade.stub
      partials/table-rows.blade.stub
    tests/
      Feature.stub
      Unit.stub
3.4 Supporting Commands
app/Console/Commands/RemovePanelCommand.php - php artisan remove:panel Vendor --confirm
app/Console/Commands/RemoveModuleCommand.php - php artisan remove:module Blog --confirm
app/Console/Commands/ListPanelsCommand.php - php artisan panels:list
app/Console/Commands/ListModulesCommand.php - php artisan modules:list
Verify:
php artisan make:panel Test creates correct structure
php artisan make:module TestModule --panels=admin creates module + admin controller
php artisan panels:list shows registered panels
php artisan modules:list shows discovered modules
php artisan remove:panel Test --confirm cleans up
php artisan remove:module TestModule --confirm cleans up
Phase 4: Admin Panel + Core Modules (~40 files)
Goal: Scaffold Admin and User panels, build core modules (UserManagement, Settings, Dashboard).

4.1 Scaffold Admin Panel
php artisan make:panel Admin then customize:


app/Panels/Admin/
  Controllers/
    DashboardController.php        - Widget data from DashboardService
    UserController.php             - CRUD using UserManagement module
    RoleController.php             - CRUD using Spatie Role model directly
    AuditLogController.php         - Read-only list + detail view
    SettingController.php          - Grouped settings form
  Middleware/
    AdminAccess.php
  Requests/
    StoreUserRequest.php
    UpdateUserRequest.php
    StoreRoleRequest.php
  routes.php                       - All admin routes
4.2 Scaffold User Panel
php artisan make:panel User then customize:


app/Panels/User/
  Controllers/
    DashboardController.php        - Simple user dashboard
    ProfileController.php          - Edit own profile
  Middleware/
    UserAccess.php
  Requests/
    UpdateProfileRequest.php
  routes.php
4.3 UserManagement Module
php artisan make:module UserManagement --panels=admin then customize:


app/Modules/UserManagement/
  Services/UserService.php         - listPaginated(), findOrFail(), create(), update(), delete(), toggleStatus()
  Actions/CreateUserAction.php     - Create user + assign role + audit log
  Actions/UpdateUserAction.php     - Update user + sync roles + audit log
  Repositories/UserRepository.php  - Eloquent queries with search/filter/sort
  Policies/UserPolicy.php         - viewAny, view, create, update, delete (using Spatie permissions)
  Database/Seeders/UserSeeder.php  - Sample users for development
  Providers/UserManagementServiceProvider.php
Note: Uses the existing User.php model - no need to create a new one.

4.4 Settings Module
php artisan make:module Settings --panels=admin then customize:


app/Modules/Settings/
  Models/Setting.php               - group, key, value, type, label, is_public
  Services/SettingsService.php     - get(), set(), getGroup(), all() with cache
  Database/Migrations/create_settings_table.php
  Database/Seeders/SettingSeeder.php  - Default settings (site_name, etc.)
  Providers/SettingsServiceProvider.php  - Registers global setting() helper
4.5 Dashboard Service
Simple service, no full module needed:


app/Modules/Shared/Services/DashboardService.php  - Total users, recent activity, role stats, system info
4.6 Panel Views (minimal, functional - design layer comes later)

resources/views/panels/admin/
  layouts/app.blade.php            - Admin layout with sidebar + header + content
  dashboard.blade.php
  users/index.blade.php
  users/create.blade.php
  users/edit.blade.php
  users/show.blade.php
  roles/index.blade.php
  roles/create.blade.php
  roles/edit.blade.php
  audit-logs/index.blade.php
  audit-logs/show.blade.php
  settings/index.blade.php

resources/views/panels/user/
  layouts/app.blade.php            - User panel layout
  dashboard.blade.php
  profile/edit.blade.php
4.7 Update config/panels.php
Add both panels with correct roles, prefixes, etc.

Verify:
Login as admin → /admin dashboard loads with widget data
/admin/users → CRUD works (list, create, edit, delete, toggle status)
/admin/roles → Create/edit roles, assign permissions
/admin/audit-logs → Shows logged actions
/admin/settings → Edit settings, values cached
Login as user → /dashboard loads, /dashboard/profile works
Admin cannot access /dashboard, user cannot access /admin
setting('site_name') returns cached value
Phase 5: Blade Components + Table System (~20 files)
Goal: Shared reusable components that all panels use.

5.1 Enhanced Table System
Keep TableConfig.php and enhance:

app/Services/Table/TableBuilder.php - Fluent API: TableBuilder::for($query)->searchable(['name','email'])->sortable(['name','created_at'])->build()
app/Services/Table/Column.php - Simple value object (field, label, sortable, searchable, format)
5.2 Shared Blade Components

resources/views/components/
  table/
    wrapper.blade.php              - Alpine.js table with AJAX partial loading
    header.blade.php               - Sortable column headers
    pagination.blade.php           - Cursor/offset pagination links
    search.blade.php               - Search input with debounce
  form/
    input.blade.php                - Text/email/password with label + error
    select.blade.php               - Dropdown with options
    textarea.blade.php
    checkbox.blade.php
    toggle.blade.php               - On/off switch
    submit.blade.php               - Submit button with loading state
  ui/
    modal.blade.php                - Alpine.js modal
    alert.blade.php                - Success/error/warning/info
    badge.blade.php                - Status badges
    button.blade.php               - Variants (primary, danger, etc.)
    card.blade.php                 - Card wrapper
    confirm.blade.php              - Delete confirmation dialog
    flash.blade.php                - Auto-dismiss flash messages
  navigation/
    sidebar.blade.php              - Collapsible sidebar with permission checks
    sidebar-item.blade.php         - Single item with active state
    header.blade.php               - Top bar with user dropdown
    breadcrumb.blade.php
5.3 Update Panel Layouts
Update resources/views/panels/admin/layouts/app.blade.php and user layout to use the shared components.

5.4 Update Module Views
Refactor the Phase 4 views to use <x-table.wrapper>, <x-form.input>, etc.

Verify:
Tables render with search, sort, pagination
Forms show validation errors correctly
Modals open/close, confirmations work
Flash messages auto-dismiss
Sidebar highlights active route, respects permissions
Phase 6: API + Testing (~20 files)
Goal: API endpoints with Sanctum + comprehensive Pest test suite.

6.1 API Auth Controller

app/Http/Controllers/Api/AuthController.php    - login (issue token), logout (revoke), me
6.2 API Resources

app/Http/Resources/
  UserResource.php
  RoleResource.php
  SettingResource.php
6.3 API Routes
routes/api.php - Versioned under api/v1/, Sanctum-guarded, auto-load module API routes.

6.4 Tests

tests/
  Feature/
    Auth/LoginTest.php             - Login, redirect, invalid credentials, inactive user
    Auth/RegisterTest.php          - Register, role assignment, validation
    Auth/LogoutTest.php            - Session invalidation
    Panels/AdminAccessTest.php     - Admin role required, staff blocked from admin actions
    Panels/UserAccessTest.php      - User panel accessible, admin panel blocked
    Admin/UserCrudTest.php         - Full CRUD + toggle status + permissions
    Admin/RoleCrudTest.php         - Role CRUD + permission assignment
    Admin/AuditLogTest.php         - Audit entries created, viewable
    Admin/SettingsTest.php         - Get/set settings, cache invalidation
    Api/AuthApiTest.php            - Token login/logout/me
  Unit/
    Services/UserServiceTest.php
    Services/SettingsServiceTest.php
    Services/AuditLogServiceTest.php
    Services/TableBuilderTest.php
  Helpers/
    TestHelper.php                 - createAdmin(), createStaff(), createUser(), seedPermissions()
6.5 CI Pipeline
.github/workflows/ci.yml - PHP 8.2/8.3 matrix, MySQL, Pest with coverage >= 80%

Verify:
php artisan test — all green
API: POST /api/v1/login returns token, GET /api/v1/me returns user
Coverage report shows >= 80%
Phase 7: Documentation (~12 files)
Goal: All development docs created in rules/docs/.


rules/docs/
  architecture/
    overview.md                    - Panel vs Module separation, request lifecycle
    folder-structure.md            - Annotated directory tree
    naming-conventions.md          - Naming rules for all file types
  guides/
    quick-start.md                 - Clone → setup → first panel + module
    module-guide.md                - make:module usage + customization
    panel-guide.md                 - make:panel usage + customization
    component-guide.md             - Blade component reference
    api-guide.md                   - API auth, versioning, resources
  tasks/
    phase-checklist.md             - All phases with status checkboxes
  changelog.md
Also update README.md with quick-start and CONTRIBUTING.md with code style.

File Count Comparison
Previous Plan	This Plan
Phase 1 (Foundation)	~30 files	~12 files
Phase 2 (Auth)	~20 files	~25 files
Phase 3 (CLI)	~25 files	~25 files
Phase 4 (Modules)	~40 files	~40 files
Phase 5 (Components)	~30 files	~20 files
Phase 6 (API + Testing)	~25 files	~20 files
Phase 7 (Docs)	~10 files	~12 files
Total	~180 files	~154 files
Key difference: Less abstraction code, no unused base classes, same functionality.

What Was Cut (and Why)
Cut	Why
BaseController	Controllers are thin - no shared logic needed. Use Laravel's Controller.
BaseModel	Just use traits directly on models that need them.
BaseRepository / RepositoryInterface	Over-engineering. Services use Eloquent directly. Repositories only where query logic is complex.
BaseService	No shared logic worth abstracting.
BaseAction	Actions are simple classes - no inheritance needed.
BaseFormRequest	Laravel's FormRequest is already the base.
Filterable trait	Use Eloquent scopes directly on models.
ViewComposerServiceProvider	PanelServiceProvider already shares panel context.
config/modules.php	Not needed - ModuleServiceProvider auto-discovers without config.
config/theme.php	Panel config has a theme key. Full theme config is premature.
config/audit.php	Simple constants in AuditLogService. Config file overkill for 3 values.
BaseException	Use Laravel's built-in exception handling.
6 separate phases for API + Testing + Docs	Combined into fewer phases.
Verification Plan
After each phase:

php artisan serve - no errors
php artisan route:list - routes correct
php artisan test - all pass (from Phase 6 onward)
Final verification:

php artisan make:panel Vendor → creates working panel scaffold
php artisan make:module Blog --panels=admin,user → creates module + panel controllers
Add 1 config line → visit /vendor → panel loads
Login as admin → full CRUD on users, roles
Login as user → dashboard + profile only
setting('site_name') → returns cached value
php artisan remove:panel Vendor --confirm → clean removal
php artisan remove:module Blog --confirm → clean removal
php artisan test --coverage → >= 80%
Implementation Order

Phase 1 (Infrastructure)  ~12 files    ← Everything depends on this
       ↓
Phase 2 (Auth + RBAC)     ~25 files    ← Panels need auth
       ↓
Phase 3 (CLI Commands)    ~25 files    ← Speeds up Phase 4
       ↓
Phase 4 (Panels + Modules) ~40 files   ← The actual app
       ↓
Phase 5 (Components)      ~20 files    ← Polish the UI
       ↓
Phase 6 (API + Testing)   ~20 files    ← Quality assurance
       ↓
Phase 7 (Documentation)   ~12 files    ← Developer guides
Total: ~154 files, 7 phases, production-ready
