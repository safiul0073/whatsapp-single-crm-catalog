# Adding a New Module

This guide walks through creating a self-contained module under `app/Modules/`. The `Catalog` module is the canonical reference — all patterns here are derived from it.

---

## Directory Structure

All paths are **PascalCase** — the framework scans for exact casing.

```
app/Modules/YourModule/
├── module.json
├── Module.php
├── Providers/
│   └── YourModuleServiceProvider.php
├── Models/
│   └── YourModel.php
├── Database/
│   ├── Migrations/
│   │   └── 2026_01_01_000001_create_your_models_table.php
│   ├── Factories/
│   │   └── YourModelFactory.php
│   └── Seeders/
│       └── YourModuleSeeder.php
├── Services/
│   └── YourModelService.php
├── Requests/
│   ├── StoreYourModelRequest.php
│   └── UpdateYourModelRequest.php
├── Policies/
│   └── YourModelPolicy.php
├── Controllers/
│   └── Admin/
│       └── YourModelsController.php
├── Routes/
│   └── admin.php
├── Resources/
│   └── views/
│       └── admin/
│           └── your-models/
│               ├── index.blade.php
│               ├── create.blade.php
│               ├── edit.blade.php
│               └── show.blade.php
└── Tests/
    └── Feature/
        └── YourModelTest.php
```

---

## Step 1 — `module.json`

Registers the module with the auto-discovery system.

```json
{
    "name": "YourModule",
    "alias": "yourmodule",
    "description": "Short description",
    "version": "1.0.0",
    "priority": 90,
    "providers": [
        "App\\Modules\\YourModule\\Providers\\YourModuleServiceProvider"
    ],
    "requires": [],
    "active": true
}
```

- `alias` — used as the view namespace prefix (e.g. `yourmodule::admin.your-models.index`)
- `priority` — controls load order; higher = later

---

## Step 2 — `Module.php`

Defines permissions, model→policy mappings, and sidebar navigation.

```php
namespace App\Modules\YourModule;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;
use App\Modules\YourModule\Models\YourModel;
use App\Modules\YourModule\Policies\YourModelPolicy;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'yourmodule';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'yourmodule.yourmodels.view'   => 'View your models',
                'yourmodule.yourmodels.create' => 'Create your models',
                'yourmodule.yourmodels.edit'   => 'Edit your models',
                'yourmodule.yourmodels.delete' => 'Delete your models',
            ],
        ];
    }

    public function policies(): array
    {
        return [
            YourModel::class => YourModelPolicy::class,
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation
            ->group('Your Module')
            ->item('Your Models', 'admin.yourmodule.your-models.index', 'ph-cube', 'yourmodule.yourmodels.view', 90);
    }
}
```

Permission keys follow the pattern `module.resource.action`.

Icons come from [Phosphor Icons](https://phosphoricons.com/) — use the `ph-` prefix.

**When a module has multiple resources**, give each its own permission group rather than sharing. For example, the Catalog module has `catalog.products.*` and `catalog.variants.*` separately — variants are not controlled by product permissions.

---

## Step 3 — Service Provider

Minimal boilerplate — `BasePanelModuleProvider` handles view hints, migrations, policies, and routes automatically.

```php
namespace App\Modules\YourModule\Providers;

use App\Modules\Shared\Support\BasePanelModuleProvider;

class YourModuleServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        //
    }
}
```

---

## Step 4 — Migration

Place under `Database/Migrations/` (PascalCase path required). Always add `is_active` for records that can be toggled, and indexes on every foreign key and frequently filtered column.

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('your_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('image')->nullable();   // file path, not a URL
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('your_models');
    }
};
```

- Store file paths (e.g. `your-models/filename.jpg`), not full URLs. Use `asset('storage/'.$model->image)` in views.
- Do **not** store price or stock on parent records — those belong on variants/SKUs.
- Composite unique constraints can be added inline: `$table->unique(['col_a', 'col_b'], 'name')`.

Run migrations: `php artisan migrate`

---

## Step 5 — Model

```php
namespace App\Modules\YourModule\Models;

use App\Modules\YourModule\Database\Factories\YourModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YourModel extends Model
{
    /** @use HasFactory<YourModelFactory> */
    use HasFactory;

    protected static function newFactory(): YourModelFactory
    {
        return YourModelFactory::new();
    }

    protected $fillable = ['name', 'slug', 'image', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
```

- Always wire `newFactory()` explicitly — do not rely on default factory discovery across module boundaries.
- Add `scopeActive()` to every model with `is_active`.
- Use `casts()` method (not `$casts` property) — required by Laravel 13.

---

## Step 6 — Policy

Type-hint `Authenticatable`, not a specific guard model.

```php
namespace App\Modules\YourModule\Policies;

use App\Modules\YourModule\Models\YourModel;
use Illuminate\Contracts\Auth\Authenticatable;

class YourModelPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('yourmodule.yourmodels.view');
    }

    public function view(Authenticatable $user, YourModel $model): bool
    {
        return $user->can('yourmodule.yourmodels.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('yourmodule.yourmodels.create');
    }

    public function update(Authenticatable $user, YourModel $model): bool
    {
        return $user->can('yourmodule.yourmodels.edit');
    }

    public function delete(Authenticatable $user, YourModel $model): bool
    {
        return $user->can('yourmodule.yourmodels.delete');
    }
}
```

---

## Step 7 — Service

`HasCrudOperations` provides `listPaginated()`, `findOrFail()`, `create()`, `update()`, `delete()`.

```php
namespace App\Modules\YourModule\Services;

use App\Modules\Shared\Traits\HasCrudOperations;
use App\Modules\YourModule\Models\YourModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class YourModelService
{
    use HasCrudOperations;

    protected string $model = YourModel::class;

    /** @var array<string> */
    protected array $searchable = ['name', 'slug'];

    /** @var array<string> */
    protected array $sortable = ['name', 'created_at'];

    protected string $defaultSortBy = 'created_at';

    protected string $defaultSortOrder = 'desc';

    protected function applyEagerLoads(Builder $query): Builder
    {
        return $query->with('someRelation');
    }

    public function deleteImage(YourModel $model): void
    {
        if ($model->image) {
            Storage::disk('public')->delete($model->image);
        }
    }
}
```

**Key rules:**
- Always override `applyEagerLoads()` when the index view renders any relation — prevents N+1.
- Put file-deletion logic in the service, not the controller.
- If the model supports multiple images with a cap (e.g. max 5), enforce the cap **inside the service method**, not only in the controller — the controller may not always be the caller.

```php
// Example: enforcing a cap inside the service
public function saveImages(YourModel $model, array $files): void
{
    $existingCount = $model->images()->count();
    $allowed = max(0, 5 - $existingCount);
    $files = array_slice($files, 0, $allowed);

    if (empty($files)) {
        return;
    }
    // ... store files
}
```

---

## Step 8 — Form Requests

```php
// StoreYourModelRequest.php
class StoreYourModelRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'slug'      => ['required', 'string', 'unique:your_models,slug'],
            'image'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'is_active' => ['boolean'],
        ];
    }
}

// UpdateYourModelRequest.php
class UpdateYourModelRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('your_model')?->id ?? $this->route('your_model');

        return [
            'name'      => ['required', 'string', 'max:255'],
            'slug'      => ['required', 'string', Rule::unique('your_models', 'slug')->ignore($id)],
            'image'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'is_active' => ['boolean'],
        ];
    }
}
```

**Image validation rules:**
- Use `image` + `mimes:jpg,jpeg,png,webp,svg` + `max:2048` for single image fields.
- For arrays of images: `['nullable', 'array', 'max:5']` on the array key, and `['image', 'mimes:...', 'max:2048']` on `images.*`.
- For ownership-scoped deletion (e.g. delete_images): use `Rule::exists('table', 'id')->where('parent_id', $parentId)` to prevent IDOR.
- For `sale_price` type fields: use `'lt:price'` to ensure it is strictly less than the base price.
- For self-referencing foreign keys (e.g. `parent_id` on categories): use `Rule::exists('table', 'id')->whereNot('id', $id)` to prevent circular references.

---

## Step 9 — Controller

`HasCrudActions` provides all CRUD actions. Override `store()` / `update()` when you need file upload handling or other custom logic.

```php
namespace App\Modules\YourModule\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Traits\HasCrudActions;
use App\Modules\YourModule\Requests\StoreYourModelRequest;
use App\Modules\YourModule\Requests\UpdateYourModelRequest;
use App\Modules\YourModule\Services\YourModelService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

class YourModelsController extends Controller implements HasMiddleware
{
    use HasCrudActions;

    protected string $viewPath = 'yourmodule::admin.your-models';
    protected string $routePrefix = 'admin.yourmodule.your-models';
    protected string $resourceName = 'your_models'; // becomes $your_models in index, $your_model in edit/show

    public static function middleware(): array
    {
        return static::crudMiddleware('yourmodule.yourmodels');
    }

    public function __construct(
        protected YourModelService $service
    ) {}

    protected function formData(): array
    {
        // Extra data passed to create/edit views (dropdowns, etc.)
        return [];
    }

    // Override show() when you need eager loads beyond the default
    public function show(mixed $record): View
    {
        $model = $this->resolveRecord($record);
        $model->load(['someRelation', 'anotherRelation']);

        return view("{$this->viewPath}.show", compact('model'));
    }

    // Override store() when handling file uploads
    public function store(StoreYourModelRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['image']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('your-models', 'public');
        }

        $this->service->create($data);

        return redirect()
            ->route("{$this->routePrefix}.index")
            ->with('success', __('Record created successfully'));
    }

    // Override update() when handling file uploads with old-file cleanup
    public function update(UpdateYourModelRequest $request, mixed $record): RedirectResponse
    {
        $record = $this->resolveRecord($record);
        $data = $request->safe()->except(['image']);

        if ($request->hasFile('image')) {
            $this->service->deleteImage($record);
            $data['image'] = $request->file('image')->store('your-models', 'public');
        }

        $this->service->update($record, $data);

        return redirect()
            ->route("{$this->routePrefix}.index")
            ->with('success', __('Record updated successfully'));
    }
}
```

**Variable naming in views:**
- Index view receives: `$your_models` (the `$resourceName` value as-is)
- Edit/show views receive: `$your_model` (auto-singularized)

---

## Step 10 — Routes

File must be at `Routes/admin.php` (capital R). The `admin.` prefix is prepended by the framework automatically — do not add it here.

```php
use App\Modules\YourModule\Controllers\Admin\YourModelsController;
use Illuminate\Support\Facades\Route;

Route::prefix('yourmodule')->name('yourmodule.')->group(function () {
    Route::resource('your-models', YourModelsController::class);
});
```

This produces routes like `admin.yourmodule.your-models.index`.

---

## Step 11 — Views

Views live under `Resources/views/` (PascalCase). The view namespace is the module `alias` from `module.json`.

### `index.blade.php`

Receives `$your_models` (paginated collection):

```blade
<x-layouts.admin :title="__('Your Models')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Your Models') }}</h1>
            <x-ui.button variant="primary" href="{{ route('admin.yourmodule.your-models.create') }}">
                <i class="ph ph-plus-circle"></i> {{ __('Add') }}
            </x-ui.button>
        </div>
        <div class="section-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100">
                            <th class="py-3 px-4 text-left font-medium text-neutral-500">{{ __('Name') }}</th>
                            <th class="py-3 px-4 text-right font-medium text-neutral-500">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-50">
                        @forelse($your_models as $model)
                            <tr class="hover:bg-neutral-50">
                                <td class="py-3 px-4 font-medium text-neutral-900">{{ $model->name }}</td>
                                <td class="py-3 px-4 text-right">
                                    <x-tables.actions>
                                        <x-tables.action icon="eye" :href="route('admin.yourmodule.your-models.show', $model)" :label="__('View')" />
                                        <x-tables.action icon="pencil-simple" :href="route('admin.yourmodule.your-models.edit', $model)" :label="__('Edit')" />
                                        <x-tables.action icon="trash" :label="__('Delete')" variant="danger"
                                            data-modal-trigger="confirmDelete-{{ $model->id }}" />
                                    </x-tables.actions>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="py-8 text-center text-neutral-400">{{ __('No records found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $your_models->links() }}</div>
        </div>
    </div>
</x-layouts.admin>
```

### `create.blade.php`

Add `enctype="multipart/form-data"` whenever the form includes a file upload.

```blade
<x-layouts.admin :title="__('Add Your Model')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Add Your Model') }}</h1>
            <x-ui.button variant="outline" href="{{ route('admin.yourmodule.your-models.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <div class="section-card">
            <form method="POST" action="{{ route('admin.yourmodule.your-models.store') }}"
                  class="space-y-4 max-w-2xl" enctype="multipart/form-data">
                @csrf
                <x-forms.input :label="__('Name')" name="name" required />
                <x-forms.input :label="__('Slug')" name="slug" required />
                <x-forms.textarea :label="__('Description')" name="description" rows="3" />
                <x-forms.file-upload
                    :label="__('Image')"
                    name="image"
                    accept="image/jpeg,image/png,image/webp,image/svg+xml"
                    :hint="__('PNG, JPG, WEBP or SVG. Max 2MB.')"
                />
                <x-forms.toggle :label="__('Active')" name="is_active" :checked="true" />

                <div class="flex items-center gap-3 pt-4 border-t border-neutral-100">
                    <x-forms.submit :label="__('Create')" />
                    <x-ui.button variant="ghost" href="{{ route('admin.yourmodule.your-models.index') }}">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
```

### `edit.blade.php`

Show the current image thumbnail before the upload field. Use `$record->image ? __('Replace Image') : __('Image')` as the label.

```blade
<x-layouts.admin :title="__('Edit Your Model')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Edit Your Model') }}</h1>
            <x-ui.button variant="outline" href="{{ route('admin.yourmodule.your-models.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <div class="section-card">
            <form method="POST" action="{{ route('admin.yourmodule.your-models.update', $your_model) }}"
                  class="space-y-4 max-w-2xl" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <x-forms.input :label="__('Name')" name="name" :value="$your_model->name" required />
                <x-forms.input :label="__('Slug')" name="slug" :value="$your_model->slug" required />
                <x-forms.textarea :label="__('Description')" name="description" :value="$your_model->description" rows="3" />

                @if($your_model->image)
                    <div>
                        <p class="form-label">{{ __('Current Image') }}</p>
                        <img src="{{ asset("storage/{$your_model->image}") }}" alt="{{ $your_model->name }}"
                             class="h-16 w-auto rounded border border-neutral-200 object-contain p-1">
                    </div>
                @endif
                <x-forms.file-upload
                    :label="$your_model->image ? __('Replace Image') : __('Image')"
                    name="image"
                    accept="image/jpeg,image/png,image/webp,image/svg+xml"
                    :hint="__('PNG, JPG, WEBP or SVG. Max 2MB.')"
                />
                <x-forms.toggle :label="__('Active')" name="is_active" :checked="$your_model->is_active" />

                <div class="flex items-center gap-3 pt-4 border-t border-neutral-100">
                    <x-forms.submit :label="__('Update')" />
                    <x-ui.button variant="ghost" href="{{ route('admin.yourmodule.your-models.index') }}">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
```

### Available form components

| Component | Usage |
|-----------|-------|
| `<x-forms.input>` | Text, email, number inputs |
| `<x-forms.textarea>` | Multi-line text |
| `<x-forms.select>` | Dropdown |
| `<x-forms.toggle>` | Boolean on/off switch — always include for `is_active` |
| `<x-forms.file-upload>` | File/image upload with drag-drop and preview |
| `<x-forms.checkbox>` | Single checkbox |
| `<x-forms.submit>` | Submit button |

**`<x-forms.file-upload>` notes:**
- Set `accept="image/jpeg,image/png,image/webp,image/svg+xml"` for image fields — this activates the built-in image preview.
- The component uses Alpine.js internally. Image preview on select is automatic when `accept` contains `image`.
- The drop handler sets `input.files` and calls `handleFiles()` directly — do not add a separate `@change` handler for the same purpose.
- **Never use `x-html` with user-supplied filenames** — file names come from user input and are an XSS vector. The component uses `x-text` via `x-show` spans instead.

---

## Step 12 — Factory

```php
namespace App\Modules\YourModule\Database\Factories;

use App\Modules\YourModule\Models\YourModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<YourModel>
 */
class YourModelFactory extends Factory
{
    protected $model = YourModel::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name'      => ucwords($name),
            'slug'      => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'image'     => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
```

**Factory rules:**
- Use `fake()->boolean(N)` (returns true N% of the time) instead of `fake()->optional(N)->something() ?? fallback`. The `optional()` proxy approach produces trailing spaces because concatenation happens before `??` is evaluated.
- Always append a `numerify` suffix to generated slugs to avoid unique constraint failures when many records are created in tests.
- For `sale_price`-style fields, use `max($min, $basePrice - 0.01)` as the upper bound to stay strictly less than the base price and match the `lt:price` validation rule.
- Add factory states for common test scenarios: `inactive()`, `featured()`, `outOfStock()`, `onSale()`.

---

## Step 13 — Seeder

Place in `Database/Seeders/` and register in `database/seeders/DatabaseSeeder.php`.

```php
namespace App\Modules\YourModule\Database\Seeders;

use App\Modules\YourModule\Models\YourModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class YourModuleSeeder extends Seeder
{
    public function run(): void
    {
        $records = [
            ['name' => 'Example One', 'description' => 'First example record.'],
            ['name' => 'Example Two', 'description' => 'Second example record.'],
        ];

        foreach ($records as $data) {
            YourModel::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name'        => $data['name'],
                    'description' => $data['description'],
                    'image'       => null,   // always be explicit about nullable fields
                    'is_active'   => true,
                ],
            );
        }
    }
}
```

**Seeder rules:**
- Always use `firstOrCreate()` — seeders must be idempotent (safe to re-run).
- Be **explicit about all nullable fields** (e.g. `'image' => null`) in the `firstOrCreate` defaults array. Omitting them is safe now but will break if the column ever gets a non-null DB default.
- Use spread syntax instead of `array_merge()` when merging small arrays: `[...$data, 'extra' => 'value']`.
- Remove unused variables — dead assignments (e.g. a brand query whose result is never used) will cause IDE warnings.

Register in `DatabaseSeeder`:

```php
// database/seeders/DatabaseSeeder.php
$this->call([
    // ... other seeders
    \App\Modules\YourModule\Database\Seeders\YourModuleSeeder::class,
]);
```

---

## Step 14 — Clear Module Cache & Run Migrations

After creating all files:

```bash
rm bootstrap/cache/modules.php
php artisan cache:clear
php artisan migrate
php artisan storage:link   # required for public disk file serving
```

The module will now appear in the admin sidebar and all routes will be registered.

---

## Common Mistakes

| Symptom | Cause | Fix |
|---|---|---|
| Routes return 404 | `Routes/` directory named `routes/` (lowercase) | Rename to `Routes/` |
| Migrations not running | `Database/Migrations/` named `database/migrations/` | Rename to PascalCase |
| `No hint path defined for [alias]` | `Resources/views/` directory missing | Create the directory and add views |
| Module not in sidebar | `adminNavigation()` missing from `Module.php` | Add the method |
| Old state after changes | Module cache stale | `rm bootstrap/cache/modules.php` |
| Undefined variable in view | Wrong `$resourceName` | Index = plural as-is; edit/show = auto-singularized |
| Images not displaying | `storage:link` not run | `php artisan storage:link` |
| Factory generates trailing spaces in names | `optional()->method() ?? fallback` pattern | Use `fake()->boolean(N) ? ... : ...` |
| File upload silently ignored | Missing `enctype="multipart/form-data"` on form | Add the attribute to every form with a file input |
| Old file left on disk after update | File deletion not in service | Add a `deleteImage()` / `deleteLogo()` method to the service and call it before storing the new file |
| Image cap exceeded via direct service call | Cap only enforced in controller | Move the cap check into the service method itself |
| XSS via filename in upload UI | `x-html` with user-controlled value | Use `x-text` or `x-show` spans — never `x-html` for user input |
| Variants/sub-resources share parent permissions | Missing dedicated permission group | Give each resource its own `module.resource.*` permission set |
