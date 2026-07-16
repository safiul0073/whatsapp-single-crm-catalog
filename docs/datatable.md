# Schema Table Guide

This is the canonical guide for list pages.

Default approach for new CRUD modules:

- define table schema in `Tables/{Module}Table.php`
- render with `<x-tables.resource :definition="$table" :items="$items" />`

Legacy `_table-rows` rendering is still supported but optional.

## Core Contracts

- `App\Modules\Shared\Support\Tables\TableDefinition`
- `App\Modules\Shared\Support\Tables\TableColumn`
- `App\Modules\Shared\Support\Tables\TableAction`
- `App\Modules\Shared\Support\Tables\TableBulkAction`
- `App\Modules\Shared\Support\Tables\TableFilters`
- `resources/views/components/tables/resource.blade.php`

Controller hook in `HasCrudActions`:

```php
protected function tableDefinition(Request $request): ?TableDefinition
{
    return ProductsTable::make();
}
```

## Minimal Example

```php
<?php

namespace App\Modules\Products\Tables;

use App\Modules\Shared\Support\Tables\TableAction;
use App\Modules\Shared\Support\Tables\TableBulkAction;
use App\Modules\Shared\Support\Tables\TableColumn;
use App\Modules\Shared\Support\Tables\TableDefinition;

class ProductsTable
{
    public static function make(): TableDefinition
    {
        return TableDefinition::make('products')
            ->searchPlaceholder('Search products...')
            ->exportUrl(route('admin.products.export'))
            ->columns([
                TableColumn::select('id', 'Select'),
                TableColumn::text('name', 'Name')->sortable(),
                TableColumn::booleanBadge('is_active', 'Status')->sortable(sortBy: 'is_active'),
                TableColumn::date('created_at', 'Created')->sortable(),
            ])
            ->actions([
                TableAction::link('edit', fn ($record) => route('admin.products.edit', $record), 'Edit')->icon('pencil-simple'),
                TableAction::delete(href: fn ($record) => route('admin.products.destroy', $record))->icon('trash'),
            ])
            ->bulkActions(
                TableBulkAction::make('products')
                    ->deleteAction(route('admin.products.bulk-delete'))
                    ->toggleAction(route('admin.products.bulk-toggle-status'))
                    ->exportAction(route('admin.products.export'))
            );
    }
}
```

View:

```blade
<x-tables.resource :definition="$table" :items="$products" />
```

## Escape Hatches

Use these only when needed:

- custom table header: `->headerView('products::admin.partials.products-header')`
- custom row renderer: `->rowView('products::admin.partials.products-rows')`
- custom cell renderer: `TableColumn::view(...)` or `->cellView(...)`
- custom filters UI: `->filters(TableFilters::view(...))`
- custom action rendering: `TableAction::make(...)->view('...')`

## Instant Use

### Add a Linked Table Column

Command:

```bash
php artisan test app/Modules/Shared/Tests/Unit/TableColumnTest.php
```

Snippet:

```php
TableColumn::text('name', 'Name')
    ->sortable()
    ->link(fn ($record) => route('admin.currencies.edit', $record));
```

Where to edit next:

- `app/Modules/{Name}/Tables/{Name}Table.php`
- route definition in `app/Modules/{Name}/Routes/admin.php`
- permission/policy for destination page

### Add a Toggle-Status Action with Dynamic Label and Confirm

Command:

```bash
php artisan test app/Modules/Shared/Tests/Unit/TableActionTest.php
```

Snippet:

```php
TableAction::toggleStatus(fn ($record) => route('admin.currencies.toggle-status', $record))
    ->icon('power')
    ->activeLabel('Deactivate')
    ->inactiveLabel('Activate')
    ->confirmTitle(fn ($record) => $record->is_active ? __('Deactivate Currency?') : __('Activate Currency?'))
    ->confirmMessage(fn ($record) => $record->is_active
        ? __('Are you sure you want to deactivate :name?', ['name' => $record->name])
        : __('Are you sure you want to activate :name?', ['name' => $record->name]));
```

Where to edit next:

- `app/Modules/{Name}/Tables/{Name}Table.php`
- `toggle-status` route in module route file
- service `toggleStatus()` behavior

### Add Custom Action Attributes (`data-*`, `target`, `rel`, JS hooks)

Command:

```bash
php artisan test app/Modules/Shared/Tests/Unit/TableActionTest.php
```

Snippet:

```php
TableAction::link('docs', fn ($record) => $record->docs_url, 'Docs')
    ->icon('book-open')
    ->attributes(fn ($record) => [
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
        'data-tracking-id' => 'docs-'.$record->id,
        'x-on:click' => "window.dispatchEvent(new CustomEvent('docs-opened'))",
    ]);
```

Where to edit next:

- `app/Modules/{Name}/Tables/{Name}Table.php`
- frontend listener that consumes your custom attributes/events
- optional custom action view if base renderer is not enough

### Use a Custom Filter Partial with Schema Table

Command:

```bash
php artisan make:module AuditLog --panels=admin
```

Snippet:

```php
use App\Modules\Shared\Support\Tables\TableFilters;

->filters(TableFilters::view(
    'audit-log::admin.partials.filters',
    fn () => ['actors' => $actors, 'events' => $events]
))
```

Where to edit next:

- `app/Modules/{Name}/Resources/views/admin/partials/filters.blade.php`
- controller `getFilters()` override for new query keys
- service `applyFilters()` override for complex conditions

### Legacy or Manual Rendering (Only When Needed)

Command:

```bash
php artisan test
```

Snippet:

```php
protected function tableDefinition(Request $request): ?TableDefinition
{
    return null;
}

protected function rowsView(): ?string
{
    return 'products::admin._table-rows';
}
```

Where to edit next:

- `app/Modules/{Name}/Resources/views/admin/_table-rows.blade.php`
- `app/Modules/{Name}/Resources/views/admin/index.blade.php`
- controller AJAX response assumptions

## Column API Notes

Common helpers:

- `TableColumn::text('name', 'Name')`
- `TableColumn::badge('status', 'Status')`
- `TableColumn::booleanBadge('is_active', 'Status')`
- `TableColumn::date('created_at', 'Created')`
- `TableColumn::number('amount', 'Amount')`
- `TableColumn::view('customer', 'Customer', 'payments::admin.columns.customer')`
- `TableColumn::make('custom', 'Custom')->value(fn ($record) => ...)`

Useful modifiers:

- `->sortable()`
- `->sortable(sortBy: 'db_column')`
- `->headerClass('...')`
- `->cellClass('...')`
- `->align('left|center|right')`
- `->responsive('hidden md:table-cell')`
- `->format(fn ($value, $record, $column) => ...)`
- `->rawHtml()`
- `->visible(fn ($record, $column) => ...)`
- `->meta([...])`
- `->link(fn ($record) => route(...), openInNewTab: true)`

## Action API Notes

Built-in action types:

- `TableAction::link(...)`
- `TableAction::button(...)`
- `TableAction::submit(...)`
- `TableAction::modal(...)`
- `TableAction::delete(...)`
- `TableAction::toggleStatus(...)`
- `TableAction::divider()`
- `TableAction::section(...)`

Useful modifiers:

- `->label(...)`
- `->icon(...)`
- `->variant(...)`
- `->method(...)`
- `->confirmTitle(...)`
- `->confirmMessage(...)`
- `->visible(...)`
- `->disabled(...)`
- `->attributes(...)`
- `->view(...)`

## Recommended Rollout

1. Use schema tables as default for new CRUD modules.
2. Keep legacy/manual rows only where page shape is truly custom.
3. Move one legacy table at a time and verify search/sort/pagination and actions.
