# Blade Components Reference

Reusable Blade components live in `resources/views/components`.

For panel-specific overrides, use:

`resources/views/panels/{panel}/components/...`

## Layouts

- `<x-layouts.admin>`
- `<x-layouts.user>`
- `<x-layouts.guest>`

## Tables (Recommended First)

### `<x-tables.resource>`

Primary table component for module CRUD screens.

Usage:

```blade
<x-tables.resource :definition="$table" :items="$products" />
```

Optional slots:

- `toolbar`
- `secondaryControls`

Supported schema features (from `TableDefinition`):

- search and per-page controls
- bulk actions
- export URL wiring
- custom filters partial
- custom header/row rendering
- inline or dropdown actions

### `<x-tables.resource-rows>`

Internal row renderer used by `HasCrudActions` during AJAX responses. You usually do not call this directly.

## Tables (Low-Level / Advanced Overrides)

Use these when you intentionally bypass schema rendering:

- `<x-tables.datatable>`
- `<x-tables.table>`
- `<x-tables.heading>`
- `<x-tables.pagination>`
- `<x-tables.bulk-actions>`
- `<x-tables.actions>`
- `<x-tables.action>`

If you use manual mode, controller should return `tableDefinition() === null` and provide `rowsView()`.

## Forms

- `<x-forms.input>`
- `<x-forms.textarea>`
- `<x-forms.select>`
- `<x-forms.toggle>`
- `<x-forms.checkbox>`
- `<x-forms.radio>`
- `<x-forms.file-upload>`
- `<x-forms.submit>`

## UI

- `<x-ui.button>`
- `<x-ui.badge>`
- `<x-ui.alert>`
- `<x-ui.card>`
- `<x-ui.kpi-card>`
- `<x-ui.modal>`
- `<x-ui.confirm>`
- `<x-ui.drawer>`
- `<x-ui.dropdown>`
- `<x-ui.toast>`
- `<x-ui.flash>`
- `<x-ui.language-switcher>`

## Navigation

- `<x-navigation.sidebar>`
- `<x-navigation.topbar>`
- `<x-navigation.sidebar-group>`
- `<x-navigation.sidebar-item>`
- `<x-navigation.breadcrumb>`

## Quick Examples

Schema table page:

```blade
<x-layouts.admin :title="__('Products')">
    <div class="section-card">
        <x-tables.resource :definition="$table" :items="$products" />
    </div>
</x-layouts.admin>
```

Manual fallback page:

```blade
<x-tables.datatable :url="route('admin.products.index')">
    <x-tables.table>
        <thead>...</thead>
        <tbody data-datatable-body>
            @include('products::admin._table-rows')
        </tbody>
    </x-tables.table>
    <x-slot:pagination>
        <x-tables.pagination :paginator="$products" />
    </x-slot:pagination>
</x-tables.datatable>
```
