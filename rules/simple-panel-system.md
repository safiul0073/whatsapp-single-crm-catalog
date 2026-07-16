# Simple Multi-Panel Boilerplate — Developer Guide

## How It Works (30-second overview)

```
config/panels.php          ← Register/unregister panels (one line each)
app/Panels/{PanelName}/    ← Each panel is a self-contained folder
app/Modules/{Feature}/     ← Shared business logic (models, services)
```

**Create panel:** `php artisan make:panel Vendor` → scaffolds everything
**Remove panel:** Delete folder + remove one line from config → done
**Create module:** `php artisan make:module Blog` → scaffolds domain + panel controllers
**Remove module:** Delete the module folder → done

---

## Complete Folder Structure

```
your-project/
│
├── app/
│   ├── Panels/                              ← ALL panels live here
│   │   │
│   │   ├── Admin/                           ← Delete this folder = Admin panel gone
│   │   │   ├── Controllers/
│   │   │   │   ├── DashboardController.php
│   │   │   │   └── UserController.php
│   │   │   ├── Middleware/
│   │   │   │   └── AdminAccess.php
│   │   │   ├── Requests/
│   │   │   ├── routes.php                   ← All admin routes in one file
│   │   │   └── PanelConfig.php              ← Panel metadata (prefix, middleware, roles)
│   │   │
│   │   ├── User/                            ← Delete this folder = User panel gone
│   │   │   ├── Controllers/
│   │   │   │   ├── DashboardController.php
│   │   │   │   └── ProfileController.php
│   │   │   ├── Middleware/
│   │   │   │   └── UserAccess.php
│   │   │   ├── Requests/
│   │   │   ├── routes.php
│   │   │   └── PanelConfig.php
│   │   │
│   │   └── Vendor/                          ← Added later via make:panel
│   │       ├── Controllers/
│   │       ├── Middleware/
│   │       ├── Requests/
│   │       ├── routes.php
│   │       └── PanelConfig.php
│   │
│   ├── Modules/                             ← Shared business logic
│   │   │
│   │   ├── UserManagement/
│   │   │   ├── Models/
│   │   │   │   └── User.php
│   │   │   ├── Services/
│   │   │   │   └── UserService.php
│   │   │   ├── Repositories/
│   │   │   │   └── UserRepository.php
│   │   │   ├── Actions/
│   │   │   │   └── CreateUserAction.php
│   │   │   ├── Policies/
│   │   │   │   └── UserPolicy.php
│   │   │   ├── Events/
│   │   │   ├── Database/
│   │   │   │   ├── Migrations/
│   │   │   │   └── Seeders/
│   │   │   └── Providers/
│   │   │       └── UserManagementServiceProvider.php
│   │   │
│   │   ├── Blog/                            ← Another module
│   │   │   ├── Models/
│   │   │   ├── Services/
│   │   │   └── ...
│   │   │
│   │   └── Shared/                          ← Cross-module shared code
│   │       ├── Models/
│   │       │   ├── Setting.php
│   │       │   └── Role.php
│   │       ├── Traits/
│   │       │   └── HasPanel.php
│   │       └── Helpers/
│   │           └── PanelHelper.php
│   │
│   └── Providers/
│       ├── AppServiceProvider.php
│       ├── PanelServiceProvider.php          ← Auto-loads all panels
│       └── ModuleServiceProvider.php         ← Auto-loads all modules
│
├── config/
│   └── panels.php                           ← Master switch for panels
│
└── resources/
    └── views/
        ├── panels/                          ← Panel views organized
        │   ├── admin/
        │   │   ├── layouts/
        │   │   │   └── app.blade.php
        │   │   ├── dashboard.blade.php
        │   │   └── users/
        │   │       ├── index.blade.php
        │   │       ├── create.blade.php
        │   │       └── edit.blade.php
        │   ├── user/
        │   │   ├── layouts/
        │   │   │   └── app.blade.php
        │   │   ├── dashboard.blade.php
        │   │   └── profile/
        │   │       └── edit.blade.php
        │   └── vendor/
        │       ├── layouts/
        │       └── ...
        └── components/                      ← Shared Blade components
            ├── table.blade.php
            ├── form-input.blade.php
            └── modal.blade.php
```

---

## Step 1: Panel Registry — `config/panels.php`

This is the **single source of truth**. Enable/disable any panel by adding/removing one line.

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registered Panels
    |--------------------------------------------------------------------------
    |
    | Add a panel  → add one line here + run make:panel
    | Remove panel → delete the line + delete the folder from app/Panels/
    |
    */

    'admin' => [
        'name'       => 'Admin Panel',
        'prefix'     => 'admin',                    // URL: yoursite.com/admin/*
        'middleware'  => ['web', 'auth', 'panel:admin'],
        'roles'      => ['super-admin', 'admin'],   // Who can access
        'guard'      => 'web',
        'theme'      => 'dark',                     // Pass to views
        'active'     => true,                       // Quick on/off toggle
    ],

    'user' => [
        'name'       => 'User Dashboard',
        'prefix'     => 'dashboard',                // URL: yoursite.com/dashboard/*
        'middleware'  => ['web', 'auth', 'panel:user'],
        'roles'      => [],                         // Empty = all authenticated users
        'guard'      => 'web',
        'theme'      => 'light',
        'active'     => true,
    ],

    // 'vendor' => [
    //     'name'       => 'Vendor Panel',
    //     'prefix'     => 'vendor',
    //     'middleware'  => ['web', 'auth', 'panel:vendor'],
    //     'roles'      => ['vendor'],
    //     'guard'      => 'web',
    //     'theme'      => 'blue',
    //     'active'     => true,
    // ],

];
```

---

## Step 2: Panel Auto-Loader — `PanelServiceProvider.php`

This provider **automatically discovers and boots every panel** listed in `config/panels.php`. Developers never touch this file.

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class PanelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (config('panels', []) as $key => $panel) {

            // Skip inactive panels
            if (empty($panel['active'])) continue;

            $panelPath = app_path("Panels/" . ucfirst($key));
            $routesFile = $panelPath . '/routes.php';
            $viewsPath = resource_path("views/panels/{$key}");

            // Register routes
            if (file_exists($routesFile)) {
                Route::middleware($panel['middleware'] ?? ['web', 'auth'])
                    ->prefix($panel['prefix'])
                    ->name("{$key}.")
                    ->group($routesFile);
            }

            // Register views with namespace → view('admin::dashboard')
            if (is_dir($viewsPath)) {
                $this->loadViewsFrom($viewsPath, $key);
            }
        }
    }

    public function register(): void
    {
        // Share current panel info globally
        $this->app->singleton('current.panel', function () {
            $prefix = request()->segment(1);
            foreach (config('panels', []) as $key => $panel) {
                if (($panel['prefix'] ?? '') === $prefix && !empty($panel['active'])) {
                    return array_merge($panel, ['key' => $key]);
                }
            }
            return null;
        });
    }
}
```

---

## Step 3: Module Auto-Loader — `ModuleServiceProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $modulesPath = app_path('Modules');

        if (!is_dir($modulesPath)) return;

        // Auto-discover and register each module's service provider
        foreach (scandir($modulesPath) as $module) {
            if ($module === '.' || $module === '..') continue;

            $providerDir = "{$modulesPath}/{$module}/Providers";
            if (!is_dir($providerDir)) continue;

            foreach (glob("{$providerDir}/*ServiceProvider.php") as $file) {
                $className = "App\\Modules\\{$module}\\Providers\\" . basename($file, '.php');
                if (class_exists($className)) {
                    $this->app->register($className);
                }
            }
        }
    }

    public function boot(): void
    {
        // Auto-load module migrations
        $modulesPath = app_path('Modules');

        if (!is_dir($modulesPath)) return;

        foreach (scandir($modulesPath) as $module) {
            if ($module === '.' || $module === '..') continue;

            $migrationsPath = "{$modulesPath}/{$module}/Database/Migrations";
            if (is_dir($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }
        }
    }
}
```

---

## Step 4: Panel Access Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PanelAccess
{
    public function handle(Request $request, Closure $next, string $panel): Response
    {
        $config = config("panels.{$panel}");

        if (!$config || empty($config['active'])) {
            abort(404); // Panel doesn't exist or is disabled
        }

        $user = $request->user();

        // Check role-based access (empty roles = all authenticated users allowed)
        $requiredRoles = $config['roles'] ?? [];

        if (!empty($requiredRoles) && !$user->hasAnyRole($requiredRoles)) {
            abort(403, "You don't have access to this panel.");
        }

        // Share panel context with all views
        view()->share('panel', $panel);
        view()->share('panelConfig', $config);

        return $next($request);
    }
}
```

Register in `bootstrap/app.php` (Laravel 11+):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'panel' => \App\Http\Middleware\PanelAccess::class,
        'role'  => \Spatie\Permission\Middleware\RoleMiddleware::class,
    ]);
})
```

---

## Step 5: What Each Panel Folder Looks Like

### Admin Panel — `app/Panels/Admin/`

**routes.php**
```php
<?php

use App\Panels\Admin\Controllers\DashboardController;
use App\Panels\Admin\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// All routes already have prefix 'admin' and middleware from PanelServiceProvider
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::resource('users', UserController::class);
Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
```

**Controllers/DashboardController.php**
```php
<?php

namespace App\Panels\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\UserManagement\Services\UserService;

class DashboardController extends Controller
{
    public function index(UserService $userService)
    {
        return view('admin::dashboard', [
            'totalUsers'  => $userService->count(),
            'recentUsers' => $userService->recent(5),
        ]);
    }
}
```

**Controllers/UserController.php**
```php
<?php

namespace App\Panels\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\UserManagement\Services\UserService;
use App\Modules\UserManagement\Actions\CreateUserAction;
use App\Modules\UserManagement\Actions\SuspendUserAction;
use App\Panels\Admin\Requests\StoreUserRequest;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService,
    ) {}

    public function index(Request $request)
    {
        $users = $this->userService->listPaginated(
            filters: $request->only(['search', 'role', 'status']),
            perPage: 25,
        );

        return view('admin::users.index', compact('users'));
    }

    public function create()
    {
        return view('admin::users.create');
    }

    public function store(StoreUserRequest $request, CreateUserAction $action)
    {
        $user = $action->execute($request->validated());

        return redirect()->route('admin.users.index')
            ->with('success', 'User created.');
    }

    public function edit($id)
    {
        $user = $this->userService->findOrFail($id);
        return view('admin::users.edit', compact('user'));
    }

    public function suspend($id, SuspendUserAction $action, Request $request)
    {
        $action->execute($id, $request->input('reason'));

        return back()->with('success', 'User suspended.');
    }
}
```

---

### User Panel — `app/Panels/User/`

**routes.php**
```php
<?php

use App\Panels\User\Controllers\DashboardController;
use App\Panels\User\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
```

**Controllers/ProfileController.php**
```php
<?php

namespace App\Panels\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\UserManagement\Services\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private ProfileService $profileService) {}

    public function edit(Request $request)
    {
        $profile = $this->profileService->getForUser($request->user());
        return view('user::profile.edit', compact('profile'));
    }

    public function update(Request $request)
    {
        $this->profileService->update($request->user(), $request->validated());
        return back()->with('success', 'Profile updated.');
    }
}
```

---

## Step 6: Module Example — `app/Modules/UserManagement/`

**Services/UserService.php** — Used by ALL panels
```php
<?php

namespace App\Modules\UserManagement\Services;

use App\Modules\UserManagement\Repositories\UserRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    public function __construct(private UserRepository $repository) {}

    public function listPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    public function findOrFail(int $id)
    {
        return $this->repository->findOrFail($id);
    }

    public function count(): int
    {
        return $this->repository->count();
    }

    public function recent(int $limit = 5)
    {
        return $this->repository->recent($limit);
    }
}
```

**Actions/CreateUserAction.php** — Single-purpose, testable
```php
<?php

namespace App\Modules\UserManagement\Actions;

use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    public function execute(array $data): User
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (!empty($data['role'])) {
            $user->assignRole($data['role']);
        }

        return $user;
    }
}
```

**Providers/UserManagementServiceProvider.php**
```php
<?php

namespace App\Modules\UserManagement\Providers;

use Illuminate\Support\ServiceProvider;

class UserManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind interfaces to implementations
        $this->app->bind(
            \App\Modules\UserManagement\Repositories\Interfaces\UserRepositoryInterface::class,
            \App\Modules\UserManagement\Repositories\UserRepository::class,
        );
    }

    public function boot(): void
    {
        // Module migrations are auto-loaded by ModuleServiceProvider
    }
}
```

---

## Step 7: Artisan Generator — `make:panel`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class MakePanel extends Command
{
    protected $signature = 'make:panel {name}';
    protected $description = 'Create a new panel with all scaffolding';

    public function __construct(private Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $key = Str::lower($name);
        $prefix = Str::kebab($name);

        $panelPath = app_path("Panels/{$name}");
        $viewsPath = resource_path("views/panels/{$key}");

        if (is_dir($panelPath)) {
            $this->error("Panel [{$name}] already exists!");
            return self::FAILURE;
        }

        // Create directories
        $this->files->makeDirectory("{$panelPath}/Controllers", 0755, true);
        $this->files->makeDirectory("{$panelPath}/Middleware", 0755, true);
        $this->files->makeDirectory("{$panelPath}/Requests", 0755, true);
        $this->files->makeDirectory("{$viewsPath}/layouts", 0755, true);

        // Create routes.php
        $this->files->put("{$panelPath}/routes.php", $this->routesStub($name, $key));

        // Create DashboardController
        $this->files->put(
            "{$panelPath}/Controllers/DashboardController.php",
            $this->controllerStub($name, $key)
        );

        // Create Access Middleware
        $this->files->put(
            "{$panelPath}/Middleware/{$name}Access.php",
            $this->middlewareStub($name)
        );

        // Create layout view
        $this->files->put(
            "{$viewsPath}/layouts/app.blade.php",
            $this->layoutStub($name, $key)
        );

        // Create dashboard view
        $this->files->put(
            "{$viewsPath}/dashboard.blade.php",
            $this->dashboardViewStub($name)
        );

        $this->info("✅ Panel [{$name}] created successfully!");
        $this->newLine();
        $this->line("  📁 Panel code:  app/Panels/{$name}/");
        $this->line("  📁 Panel views: resources/views/panels/{$key}/");
        $this->newLine();
        $this->warn("  👉 Add this to config/panels.php:");
        $this->newLine();
        $this->line("  '{$key}' => [");
        $this->line("      'name'       => '{$name} Panel',");
        $this->line("      'prefix'     => '{$prefix}',");
        $this->line("      'middleware'  => ['web', 'auth', 'panel:{$key}'],");
        $this->line("      'roles'      => ['{$key}'],");
        $this->line("      'guard'      => 'web',");
        $this->line("      'theme'      => 'light',");
        $this->line("      'active'     => true,");
        $this->line("  ],");

        return self::SUCCESS;
    }

    private function routesStub(string $name, string $key): string
    {
        return <<<PHP
        <?php

        use App\Panels\\{$name}\Controllers\DashboardController;
        use Illuminate\Support\Facades\Route;

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        PHP;
    }

    private function controllerStub(string $name, string $key): string
    {
        return <<<PHP
        <?php

        namespace App\Panels\\{$name}\Controllers;

        use App\Http\Controllers\Controller;

        class DashboardController extends Controller
        {
            public function index()
            {
                return view('{$key}::dashboard');
            }
        }

        PHP;
    }

    private function middlewareStub(string $name): string
    {
        return <<<PHP
        <?php

        namespace App\Panels\\{$name}\Middleware;

        use Closure;
        use Illuminate\Http\Request;

        class {$name}Access
        {
            public function handle(Request \$request, Closure \$next)
            {
                // Add panel-specific access logic here
                return \$next(\$request);
            }
        }

        PHP;
    }

    private function layoutStub(string $name, string $key): string
    {
        return <<<'BLADE'
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>@yield('title') - PANEL_NAME</title>
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        </head>
        <body>
            <div class="wrapper">
                @include('KEY::layouts.partials.sidebar', ['panel' => 'KEY'])
                <main>
                    @yield('content')
                </main>
            </div>
        </body>
        </html>
        BLADE;
    }

    private function dashboardViewStub(string $name): string
    {
        return <<<'BLADE'
        @extends($panel . '::layouts.app')

        @section('title', 'Dashboard')

        @section('content')
            <h1>PANEL_NAME Dashboard</h1>
            <p>Welcome, {{ auth()->user()->name }}</p>
        @endsection
        BLADE;
    }
}
```

---

## Step 8: Artisan Generator — `make:module`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class MakeModule extends Command
{
    protected $signature = 'make:module {name} {--panels=admin : Comma-separated panels to scaffold controllers for}';
    protected $description = 'Create a new feature module';

    public function __construct(private Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $panels = array_map('trim', explode(',', $this->option('panels')));

        $modulePath = app_path("Modules/{$name}");

        if (is_dir($modulePath)) {
            $this->error("Module [{$name}] already exists!");
            return self::FAILURE;
        }

        // Create module structure
        $dirs = [
            'Models', 'Services', 'Actions',
            'Repositories/Interfaces', 'Policies',
            'Events', 'Database/Migrations', 'Database/Seeders',
            'Providers',
        ];

        foreach ($dirs as $dir) {
            $this->files->makeDirectory("{$modulePath}/{$dir}", 0755, true);
        }

        // Create ServiceProvider
        $this->files->put(
            "{$modulePath}/Providers/{$name}ServiceProvider.php",
            $this->serviceProviderStub($name)
        );

        // Scaffold panel controllers for this module
        foreach ($panels as $panel) {
            $panel = Str::studly($panel);
            $panelPath = app_path("Panels/{$panel}/Controllers");

            if (is_dir($panelPath)) {
                $this->files->put(
                    "{$panelPath}/{$name}Controller.php",
                    $this->panelControllerStub($name, $panel)
                );
                $this->info("  → Controller created in Panels/{$panel}/");
            } else {
                $this->warn("  → Panel [{$panel}] not found, skipping controller.");
            }
        }

        $this->info("✅ Module [{$name}] created at app/Modules/{$name}/");
        $this->line("   Auto-discovered by ModuleServiceProvider. No registration needed.");

        return self::SUCCESS;
    }

    private function serviceProviderStub(string $name): string
    {
        return <<<PHP
        <?php

        namespace App\Modules\\{$name}\Providers;

        use Illuminate\Support\ServiceProvider;

        class {$name}ServiceProvider extends ServiceProvider
        {
            public function register(): void
            {
                //
            }

            public function boot(): void
            {
                //
            }
        }

        PHP;
    }

    private function panelControllerStub(string $module, string $panel): string
    {
        $key = Str::lower($panel);
        return <<<PHP
        <?php

        namespace App\Panels\\{$panel}\Controllers;

        use App\Http\Controllers\Controller;
        use Illuminate\Http\Request;

        class {$module}Controller extends Controller
        {
            public function index()
            {
                return view('{$key}::{$this->viewFolder($module)}.index');
            }
        }

        PHP;
    }

    private function viewFolder(string $name): string
    {
        return Str::kebab(Str::plural($name));
    }
}
```

---

## Developer Workflow Examples

### "I need to add a Vendor panel to this project"

```bash
# Step 1: Scaffold
php artisan make:panel Vendor

# Step 2: Add to config/panels.php
'vendor' => [
    'name'       => 'Vendor Panel',
    'prefix'     => 'vendor',
    'middleware'  => ['web', 'auth', 'panel:vendor'],
    'roles'      => ['vendor'],
    'guard'      => 'web',
    'theme'      => 'blue',
    'active'     => true,
],

# Step 3: Done. Visit /vendor
```

### "I need to remove the Vendor panel"

```bash
# Step 1: Delete the folder
rm -rf app/Panels/Vendor
rm -rf resources/views/panels/vendor

# Step 2: Remove from config/panels.php (or set 'active' => false)

# Done. No other files to touch.
```

### "I need a new Blog module accessible from Admin and User panels"

```bash
php artisan make:module Blog --panels=admin,user

# Creates:
#   app/Modules/Blog/Models/
#   app/Modules/Blog/Services/
#   app/Modules/Blog/Actions/
#   ...
#   app/Panels/Admin/Controllers/BlogController.php    ← Admin CRUD
#   app/Panels/User/Controllers/BlogController.php     ← User view
```

### "I want to temporarily disable the Vendor panel"

```php
// config/panels.php — just flip one flag
'vendor' => [
    ...
    'active' => false,  // Panel is now invisible
],
```

---

## How Panels and Modules Connect (Visual)

```
┌─────────────────────────────────────────────────────────────────┐
│                        config/panels.php                         │
│              (Master switch: which panels are active)             │
└────────┬──────────────────┬──────────────────┬──────────────────┘
         │                  │                  │
         ▼                  ▼                  ▼
┌─────────────┐   ┌─────────────┐   ┌─────────────┐
│ Admin Panel │   │ User Panel  │   │Vendor Panel │    ← Presentation
│ /admin/*    │   │ /dashboard/*│   │ /vendor/*   │       (Controllers,
│ Controllers │   │ Controllers │   │ Controllers │        Views, Routes)
│ Views       │   │ Views       │   │ Views       │
└──────┬──────┘   └──────┬──────┘   └──────┬──────┘
       │                 │                 │
       │    ┌────────────┼────────────┐    │
       │    │            │            │    │
       ▼    ▼            ▼            ▼    ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│    User      │  │    Blog      │  │   Product    │   ← Domain Logic
│  Management  │  │   Module     │  │   Module     │      (Models, Services,
│   Module     │  │              │  │              │       Actions, Policies)
└──────────────┘  └──────────────┘  └──────────────┘
       │                 │                 │
       └─────────────────┼─────────────────┘
                         ▼
                  ┌──────────────┐
                  │   Database   │
                  └──────────────┘
```

---

## PSR-4 Autoloading — `composer.json`

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "App\\Modules\\": "app/Modules/",
            "App\\Panels\\": "app/Panels/"
        }
    }
}
```

Run `composer dump-autoload` after adding this.

---

## Register Providers — `bootstrap/providers.php`

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\PanelServiceProvider::class,    // ← Auto-loads panels
    App\Providers\ModuleServiceProvider::class,   // ← Auto-loads modules
];
```

---

## Removal Checklist

### Remove a Panel
- [ ] Delete `app/Panels/{PanelName}/`
- [ ] Delete `resources/views/panels/{panelname}/`
- [ ] Remove entry from `config/panels.php`

### Remove a Module
- [ ] Delete `app/Modules/{ModuleName}/`
- [ ] Delete related controllers from each `app/Panels/*/Controllers/`
- [ ] Delete related views from `resources/views/panels/*/`
- [ ] Run `php artisan migrate:rollback` for module migrations if needed

---

## Summary

| Task | Steps |
|------|-------|
| **Add panel** | 1 command + 1 config line |
| **Remove panel** | Delete 1 folder + 1 config line |
| **Disable panel** | Change `'active' => false` |
| **Add module** | 1 command (auto-discovered) |
| **Remove module** | Delete 1 folder |
| **Add module to panel** | Create controller in panel + add routes |
