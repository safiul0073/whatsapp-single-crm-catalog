# Services Module — Implementation Plan

## Overview

The **Services** module manages the agency's service offerings, organized into categories. Each category contains multiple services. This module is purely admin-facing for now; frontend rendering is deferred to a follow-up task.

**Future compatibility:** A future `Products` module will link projects/case studies to a service via a `service_id` FK on the products table. No changes to this module are needed for that.

---

## Directory Structure

```
app/Modules/Services/
├── module.json
├── Module.php
├── Providers/
│   └── ServicesServiceProvider.php
├── Models/
│   ├── ServiceCategory.php
│   └── Service.php
├── Http/
│   ├── Controllers/
│   │   └── Admin/
│   │       ├── ServiceCategoriesController.php
│   │       └── ServicesController.php
│   └── Requests/
│       ├── StoreServiceCategoryRequest.php
│       ├── UpdateServiceCategoryRequest.php
│       ├── StoreServiceRequest.php
│       └── UpdateServiceRequest.php
├── Routes/
│   └── admin.php
├── Tables/
│   ├── ServiceCategoriesTable.php
│   └── ServicesTable.php
├── Database/
│   ├── Migrations/
│   │   ├── xxxx_create_service_categories_table.php
│   │   └── xxxx_create_services_table.php
│   └── Seeders/
│       └── ServiceCategorySeeder.php
└── Resources/
    └── views/
        └── admin/
            ├── categories/
            │   ├── index.blade.php
            │   ├── create.blade.php
            │   ├── edit.blade.php
            │   └── _table-rows.blade.php
            └── services/
                ├── index.blade.php
                ├── create.blade.php
                ├── edit.blade.php
                └── _table-rows.blade.php
```

---

## Step 1 — module.json

**File:** `app/Modules/Services/module.json`

```json
{
    "name": "Services",
    "alias": "services",
    "version": "1.0.0",
    "active": true,
    "providers": [
        "App\\Modules\\Services\\Providers\\ServicesServiceProvider"
    ],
    "requires": []
}
```

> No `requires` entries — no boot-time dependency on Frontend or Media needed at this stage.

---

## Step 2 — Module.php (Descriptor)

**File:** `app/Modules/Services/Module.php`

Extends `App\Modules\Shared\Support\BasePanelModule`. Pattern mirrors `app/Modules/Staffs/Module.php`.

### Permissions (admin guard)

| Permission key | Label |
|---|---|
| `service-categories.view` | View Service Categories |
| `service-categories.create` | Create Service Categories |
| `service-categories.edit` | Edit Service Categories |
| `service-categories.delete` | Delete Service Categories |
| `services.view` | View Services |
| `services.create` | Create Services |
| `services.edit` | Edit Services |
| `services.delete` | Delete Services |

### Admin Navigation

Group: **Content**

| Label | Route pattern | Icon | Order |
|---|---|---|---|
| Service Categories | `admin.service-categories.*` | `ph-squares-four` | 50 |
| Services | `admin.services.*` | `ph-briefcase` | 51 |

---

## Step 3 — ServicesServiceProvider.php

**File:** `app/Modules/Services/Providers/ServicesServiceProvider.php`

Extends `App\Modules\Shared\Support\BasePanelModuleProvider`.

No custom `bootModule()` needed — the base class auto-handles views, migrations, and route registration.

---

## Step 4 — Database Migrations

### Table: `service_categories`

| Column | Type | Options |
|---|---|---|
| `id` | bigIncrements | PK |
| `name` | string | |
| `slug` | string | unique |
| `description` | text | nullable |
| `icon` | string | nullable — phosphor icon name e.g. `ph-smartphone` |
| `sort_order` | unsignedInteger | default 0 |
| `active` | boolean | default true |
| `created_at` / `updated_at` | timestamps | |

### Table: `services`

| Column | Type | Options |
|---|---|---|
| `id` | bigIncrements | PK |
| `service_category_id` | foreignId | constrained → `service_categories`, cascadeOnDelete |
| `name` | string | |
| `slug` | string | unique |
| `description` | text | nullable |
| `excerpt` | string | nullable — short tagline shown in cards |
| `icon` | string | nullable — phosphor icon name |
| `accent_color` | string | nullable — e.g. `brand-blue`, `brand-green` |
| `features` | json | nullable — stored as array of bullet strings |
| `cta_label` | string | nullable |
| `cta_url` | string | nullable |
| `sort_order` | unsignedInteger | default 0 |
| `active` | boolean | default true |
| `created_at` / `updated_at` | timestamps | |

---

## Step 5 — Models

### ServiceCategory.php

**File:** `app/Modules/Services/Models/ServiceCategory.php`

- `$fillable`: `name`, `slug`, `description`, `icon`, `sort_order`, `active`
- `$casts`: `active => boolean`
- **Slug:** auto-generated in `booted()` via `Str::slug($model->name)` if not set — same pattern as `FrontendSection` and `FrontendMenu`
- **Relationships:**
  - `services()` → `HasMany(Service::class)` ordered by `sort_order`
- **Scopes:**
  - `scopeActive($q)` → `$q->where('active', true)`

### Service.php

**File:** `app/Modules/Services/Models/Service.php`

- `$fillable`: `service_category_id`, `name`, `slug`, `description`, `excerpt`, `icon`, `accent_color`, `features`, `cta_label`, `cta_url`, `sort_order`, `active`
- `$casts`: `features => array`, `active => boolean`
- **Slug:** auto-generated in `booted()` — same pattern as above
- **Relationships:**
  - `category()` → `BelongsTo(ServiceCategory::class)`
- **Scopes:**
  - `scopeActive($q)` → `$q->where('active', true)`

---

## Step 6 — Routes

**File:** `app/Modules/Services/Routes/admin.php`

```php
Route::resource('service-categories', ServiceCategoriesController::class)->except(['show']);
Route::post('service-categories/bulk-delete', [ServiceCategoriesController::class, 'bulkDelete'])->name('service-categories.bulk-delete');
Route::post('service-categories/{serviceCategory}/toggle-status', [ServiceCategoriesController::class, 'toggleStatus'])->name('service-categories.toggle-status');

Route::resource('services', ServicesController::class)->except(['show']);
Route::post('services/bulk-delete', [ServicesController::class, 'bulkDelete'])->name('services.bulk-delete');
Route::post('services/{service}/toggle-status', [ServicesController::class, 'toggleStatus'])->name('services.toggle-status');
```

> Routes are auto-registered by `BasePanelModuleProvider` with `admin.` prefix and middleware `['web', 'auth:admin', '2fa', 'panel:admin']` from `config/panels.php`.

---

## Step 7 — Controllers

Both controllers follow the exact `StaffsController` pattern (`app/Modules/Staffs/Http/Controllers/Admin/StaffsController.php`).

### ServiceCategoriesController

**File:** `app/Modules/Services/Http/Controllers/Admin/ServiceCategoriesController.php`

| Method | Permission middleware | Description |
|---|---|---|
| `index()` | `service-categories.view` | Paginated list, supports search + sort + AJAX |
| `create()` | `service-categories.create` | Show create form |
| `store(StoreServiceCategoryRequest)` | `service-categories.create` | Save, redirect to index |
| `edit(ServiceCategory)` | `service-categories.edit` | Show edit form |
| `update(UpdateServiceCategoryRequest, ServiceCategory)` | `service-categories.edit` | Update, redirect to index |
| `destroy(ServiceCategory)` | `service-categories.delete` | Delete, redirect to index |
| `toggleStatus(ServiceCategory)` | `service-categories.edit` | Toggle `active`, redirect back |
| `bulkDelete(Request)` | — | JSON response with count |

### ServicesController

**File:** `app/Modules/Services/Http/Controllers/Admin/ServicesController.php`

Same method set as above with `services.*` permissions. Additionally:
- `create()` and `edit()` load `ServiceCategory::orderBy('name')->get()` and pass as `$categories` for the dropdown.
- `index()` eager-loads `category` relationship for display in the table.

---

## Step 8 — Form Requests

All extend `Illuminate\Foundation\Http\FormRequest` with `authorize(): true`.

### StoreServiceCategoryRequest

| Field | Rules |
|---|---|
| `name` | `required\|string\|max:255` |
| `slug` | `nullable\|string\|max:255\|unique:service_categories,slug` |
| `description` | `nullable\|string` |
| `icon` | `nullable\|string\|max:100` |
| `sort_order` | `nullable\|integer\|min:0` |
| `active` | `nullable\|boolean` |

### UpdateServiceCategoryRequest

Same as Store but slug rule: `unique:service_categories,slug,{id}`.

### StoreServiceRequest

| Field | Rules |
|---|---|
| `service_category_id` | `required\|integer\|exists:service_categories,id` |
| `name` | `required\|string\|max:255` |
| `slug` | `nullable\|string\|max:255\|unique:services,slug` |
| `description` | `nullable\|string` |
| `excerpt` | `nullable\|string\|max:500` |
| `icon` | `nullable\|string\|max:100` |
| `accent_color` | `nullable\|string\|max:50` |
| `features` | `nullable\|array` |
| `features.*` | `string\|max:255` |
| `cta_label` | `nullable\|string\|max:100` |
| `cta_url` | `nullable\|string\|max:2048` |
| `sort_order` | `nullable\|integer\|min:0` |
| `active` | `nullable\|boolean` |

### UpdateServiceRequest

Same as Store but slug rule: `unique:services,slug,{id}`.

> **Features input:** The Blade form uses a textarea (one feature per line). The controller splits on newlines before passing to the request, or the request mutates the input — whichever is cleaner.

---

## Step 9 — Table Definitions

Pattern: `App\Modules\Staffs\Tables\StaffsTable` using `TableDefinition`, `TableColumn`, `TableAction`, `TableBulkAction` from `app/Modules/Shared/Support/Tables/`.

### ServiceCategoriesTable

**Columns:** select checkbox, name (sortable), icon, sort_order, status badge (`active`), created_at (sortable)

**Actions:** edit (link), delete (confirm modal)

**Bulk actions:** delete → `route('admin.service-categories.bulk-delete')`

### ServicesTable

**Columns:** select checkbox, name (sortable), category name (via eager load), excerpt, status badge (`active`), created_at (sortable)

**Actions:** edit (link), delete (confirm modal)

**Bulk actions:** delete → `route('admin.services.bulk-delete')`

---

## Step 10 — Blade Views

All views follow the pattern in `app/Modules/Staffs/Resources/views/admin/`.

### Common components used

| Component | Purpose |
|---|---|
| `<x-layouts.admin :title="...">` | Page layout wrapper |
| `<div class="section-card">` | Card container |
| `<x-ui.button>` | Buttons (primary, outline, ghost) |
| `<x-forms.input>` | Text inputs |
| `<x-forms.textarea>` | Textarea inputs |
| `<x-forms.select>` | Dropdowns (for category on services) |
| `<x-forms.toggle>` | Boolean toggles (active status) |
| `<x-forms.submit>` | Submit button |
| `<x-tables.datatable>` | AJAX datatable wrapper |
| `<x-tables.table>` | Table element |
| `<x-tables.heading>` | Sortable column header |
| `<x-tables.actions>` | Row action group |
| `<x-tables.action>` | Single row action |
| `<x-ui.badge>` | Status badge (success/danger) |
| `<x-ui.confirm>` | Delete confirm modal |

### Features textarea

On the service create/edit form, `features` is a `<x-forms.textarea>` where each line = one bullet. The controller converts:
- **Store/Update:** `explode("\n", $request->features_text)` → filtered, trimmed array → stored as JSON
- **Edit prefill:** `implode("\n", $service->features ?? [])` → textarea value

### View files

| File | Notes |
|---|---|
| `admin/categories/index.blade.php` | Table + "Add Category" button |
| `admin/categories/create.blade.php` | Form: name, slug, description, icon, sort_order, active toggle |
| `admin/categories/edit.blade.php` | Same form pre-filled |
| `admin/categories/_table-rows.blade.php` | `@forelse` rows for AJAX refresh |
| `admin/services/index.blade.php` | Table + "Add Service" button |
| `admin/services/create.blade.php` | Form: category select, name, slug, excerpt, description, icon, accent_color, features textarea, cta_label, cta_url, sort_order, active toggle |
| `admin/services/edit.blade.php` | Same form pre-filled |
| `admin/services/_table-rows.blade.php` | `@forelse` rows for AJAX refresh |

---

## Step 11 — Frontend Section (Deferred)

The `services_grid` section type (rendering service cards from DB on the public frontend) is **out of scope** for this module's initial implementation. It will be added in a follow-up task by:

1. Adding a `services_grid` entry to `config/frontend-sections.php`
2. Creating a Blade view at `resources/views/frontend/themes/softivus/sections/services_grid.blade.php` that calls `Service::active()->with('category')->orderBy('sort_order')->get()`

---

## Verification Checklist

After implementation, verify the following:

```
[ ] php artisan migrate
      → service_categories and services tables created

[ ] php artisan permission:sync
      → 8 new permissions visible in DB

[ ] Visit /admin/service-categories
      → index page loads with empty table

[ ] Create a category
      → slug auto-generated, appears in table

[ ] Edit a category
      → existing data pre-filled, update saves correctly

[ ] Delete a category
      → confirm modal appears, record removed

[ ] Toggle status on a category
      → badge flips Active ↔ Inactive

[ ] Bulk-delete categories
      → AJAX removes selected rows, shows count message

[ ] Visit /admin/services
      → index page loads

[ ] Create a service
      → category dropdown populated, features saved as JSON array

[ ] Edit a service
      → features textarea shows one line per bullet

[ ] Category relationship shown in services table

[ ] php artisan module:disable Services
      → /admin/service-categories returns 404, nav items hidden

[ ] php artisan module:enable Services
      → routes and nav restore
```
