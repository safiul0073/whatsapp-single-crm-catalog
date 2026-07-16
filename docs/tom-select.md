# Tom Select — Searchable Dropdowns

Complete reference for Tom Select integration. A lightweight vanilla JS library — zero jQuery dependency.

**Important:** Tom Select has its own dedicated Blade component `<x-forms.tom-select>`. Do NOT add `ts-basic`/`ts-multi` classes to `<x-forms.select>` — use the dedicated component instead. `<x-forms.select>` is reserved for native styled selects.

---

## Table of Contents

1. [Overview](#overview)
2. [Quick Start](#quick-start)
3. [`<x-forms.tom-select>` Component](#component-reference)
4. [Single Searchable Select](#single-searchable-select)
5. [Multi-Select with Tags](#multi-select-with-tags)
6. [Settings Page Selects](#settings-page-selects)
7. [Configuration Options](#configuration-options)
8. [Programmatic Control](#programmatic-control)
9. [Re-initialization Events](#re-initialization-events)
10. [Styling & Customization](#styling--customization)
11. [Dark Mode & RTL](#dark-mode--rtl)
12. [Troubleshooting](#troubleshooting)

---

## Overview

### Two Select Components

| Component | Class | Use Case |
|-----------|-------|----------|
| `<x-forms.select>` | `select-field` | Native styled select — small option lists, no search needed |
| `<x-forms.tom-select>` | `ts-basic` / `ts-multi` | Searchable select — large lists, multi-select with tags |

### Key Files

| File | Purpose |
|------|---------|
| `resources/views/components/forms/tom-select.blade.php` | `<x-forms.tom-select>` Blade component |
| `resources/views/components/forms/select.blade.php` | `<x-forms.select>` native select component |
| `resources/js/components/tom-select-init.js` | Auto-initialization script |
| `resources/css/components/tom-select-custom.css` | Theme overrides matching admin panel design |

### How It Works

1. `<x-forms.tom-select>` renders a `<select>` with class `ts-basic` (or `ts-multi` for multiple)
2. The init script finds all `.ts-basic` / `.ts-multi` elements and initializes Tom Select
3. Tom Select hides the native `<select>` and renders its own searchable UI
4. The original `<select>` value stays synced — form submission works normally

---

## Quick Start

```blade
{{-- Searchable single select --}}
<x-forms.tom-select name="country" label="Country" :options="$countries" :selected="$user->country" />

{{-- Searchable multi-select --}}
<x-forms.tom-select name="tags[]" label="Tags" :options="$tags" :selected="$selectedTagIds" multiple />

{{-- Native select (no search, for small lists) --}}
<x-forms.select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" />
```

---

## Component Reference

### `<x-forms.tom-select>`

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `name` | string | **required** | Form field name (add `[]` suffix for multi) |
| `label` | string | `''` | Label text (empty = no label shown) |
| `options` | array | `[]` | Key-value pairs `[value => label]` |
| `selected` | string/array | `''` | Pre-selected value(s) |
| `required` | bool | `false` | Show required indicator on label |
| `placeholder` | string | `'Select...'` | Placeholder text (single select only) |
| `multiple` | bool | `false` | Enable multi-select with tags |

#### Using `options` Prop (Simple)

Pass a `[value => label]` array:

```blade
<x-forms.tom-select
    name="country"
    label="Country"
    :options="['us' => 'United States', 'uk' => 'United Kingdom', 'de' => 'Germany']"
    selected="us"
/>
```

#### Using Slot (Custom Options)

For more control over `<option>` elements, use the slot:

```blade
<x-forms.tom-select name="user_id" label="Assign To">
    @foreach($users as $user)
        <option value="{{ $user->id }}" @selected($task->user_id === $user->id)>
            {{ $user->name }}
        </option>
    @endforeach
</x-forms.tom-select>
```

#### Multi-Select

```blade
<x-forms.tom-select
    name="roles[]"
    label="Roles"
    :options="$roles"
    :selected="$user->roles->pluck('id')->toArray()"
    multiple
/>
```

Or with slot:

```blade
<x-forms.tom-select name="permissions[]" label="Permissions" multiple>
    @foreach($permissions as $perm)
        <option value="{{ $perm->id }}" @selected($role->permissions->contains($perm->id))>
            {{ $perm->name }}
        </option>
    @endforeach
</x-forms.tom-select>
```

---

## Single Searchable Select

Best for dropdowns with many options (10+) where typing to filter is helpful.

### With Options Array

```blade
<x-forms.tom-select
    name="timezone"
    label="Timezone"
    :options="collect(timezone_identifiers_list())->mapWithKeys(fn($tz) => [$tz => $tz])->toArray()"
    :selected="$settings->timezone"
/>
```

### With Dynamic Options from Database

```blade
<x-forms.tom-select
    name="category_id"
    label="Category"
    :options="$categories->pluck('name', 'id')->toArray()"
    :selected="$product->category_id"
    required
/>
```

---

## Multi-Select with Tags

Renders selected items as removable tag chips.

```blade
<x-forms.tom-select
    name="tags[]"
    label="Tags"
    :options="$allTags->pluck('name', 'id')->toArray()"
    :selected="$post->tags->pluck('id')->toArray()"
    multiple
/>
```

### Tag Styling

Tags use the primary color scheme:
- Tag chip: `bg-primary/10 border-primary/20 text-primary` with rounded corners
- Remove button (x): appears on each tag, hover changes to `bg-primary/20`

---

## Settings Page Selects

In the settings page, select fields with large option lists automatically use `<x-forms.tom-select>`. This is handled in `resources/views/panels/admin/settings/index.blade.php`.

### How It Works

The settings view checks each select field:
- If `options_resolver` is set (e.g., `'timezones'`) → uses `<x-forms.tom-select>`
- If static `options` array has more than 10 items → uses `<x-forms.tom-select>`
- Otherwise → uses `<x-forms.select>` (native)

### Config Example

```php
// config/settings.php
'default_timezone' => [
    'label' => 'Default Timezone',
    'type' => 'select',
    'options_resolver' => 'timezones',  // large list → auto Tom Select
    'value' => 'UTC',
],

'date_format' => [
    'label' => 'Date Format',
    'type' => 'select',
    'options' => [                      // small list → native select
        'd M, Y' => '23 Feb, 2026',
        'M d, Y' => 'Feb 23, 2026',
        'Y-m-d'  => '2026-02-23',
    ],
    'value' => 'd M, Y',
],
```

---

## Configuration Options

### Default Configs Applied by Init Script

**Single select** (`ts-basic`):

```js
{ allowEmptyOption: true, controlClass: 'ts-control', dropdownClass: 'ts-dropdown' }
```

**Multi-select** (`ts-multi`):

```js
{ plugins: ['remove_button'], controlClass: 'ts-control', dropdownClass: 'ts-dropdown' }
```

### Custom Configuration

For options beyond the defaults, initialize Tom Select manually on a plain `<select>` (without `ts-basic`/`ts-multi` class):

```blade
<select id="custom-select" name="product">
    @foreach($products as $product)
        <option value="{{ $product->id }}">{{ $product->name }}</option>
    @endforeach
</select>

@push('scripts')
<script type="module">
    import TomSelect from 'tom-select';

    new TomSelect('#custom-select', {
        maxItems: 3,
        create: true,
        sortField: 'text',
        maxOptions: 50,
        plugins: ['remove_button', 'clear_button'],
    });
</script>
@endpush
```

### Common Options Reference

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `allowEmptyOption` | bool | `true` | Allow selecting the placeholder/empty option |
| `create` | bool | `false` | Allow typing to create new options |
| `maxItems` | int | `1`/`null` | Max selected items (null = unlimited for multi) |
| `maxOptions` | int | `50` | Max options shown in dropdown |
| `sortField` | string | `'$order'` | Sort options (`'text'` for alphabetical) |
| `placeholder` | string | — | Placeholder text |
| `plugins` | array | `[]` | Plugins: `remove_button`, `clear_button`, `dropdown_input` |
| `closeAfterSelect` | bool | `true` | Close dropdown after selecting |
| `hideSelected` | bool | `false` | Hide already-selected options from dropdown |
| `render` | object | — | Custom render functions for options |

### Custom Option Rendering

```js
new TomSelect('#user-select', {
    render: {
        option: function(data, escape) {
            return `<div class="flex items-center gap-3 py-1">
                <img src="${escape(data.avatar)}" class="w-6 h-6 rounded-full">
                <div>
                    <div class="font-medium">${escape(data.text)}</div>
                    <div class="text-xs text-neutral-400">${escape(data.email)}</div>
                </div>
            </div>`;
        },
        item: function(data, escape) {
            return `<div class="flex items-center gap-2">
                <img src="${escape(data.avatar)}" class="w-4 h-4 rounded-full">
                ${escape(data.text)}
            </div>`;
        }
    }
});
```

Options can carry custom data via attributes:

```blade
<option value="{{ $user->id }}" data-avatar="{{ $user->avatar_url }}" data-email="{{ $user->email }}">
    {{ $user->name }}
</option>
```

### AJAX / Remote Search

```js
new TomSelect('#ajax-select', {
    valueField: 'id',
    labelField: 'name',
    searchField: 'name',
    load: function(query, callback) {
        if (!query.length) return callback();
        fetch(`/api/search?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => callback(data))
            .catch(() => callback());
    }
});
```

---

## Programmatic Control

```js
const el = document.querySelector('#my-select');
const ts = el.tomselect;  // Tom Select instance

ts.setValue('us');                                      // Set value
ts.addOption({ value: 'new', text: 'New Option' });    // Add option
ts.clear();                                             // Clear selection
ts.disable();                                           // Disable
ts.enable();                                            // Enable
ts.destroy();                                           // Revert to native select
```

### Refresh After Dynamic Content

```js
ts.clearOptions();
ts.addOption([
    { value: '1', text: 'Option A' },
    { value: '2', text: 'Option B' },
]);
ts.refreshOptions(false);
```

---

## Re-initialization Events

The init script auto-reinitializes on these events:

| Event | When | Dispatched By |
|-------|------|---------------|
| `DOMContentLoaded` | Page load | Browser |
| `rtl-toggled` | RTL direction toggle | Settings JS |
| `datatable:updated` | DataTable AJAX content swap | `datatable.js` |

For dynamically added selects, dispatch the event or call `initTomSelect()`:

```js
// Option A: dispatch event
document.dispatchEvent(new CustomEvent('datatable:updated'));

// Option B: direct call
import { initTomSelect } from './components/tom-select-init.js';
initTomSelect();
```

---

## Styling & Customization

All styles are in `resources/css/components/tom-select-custom.css`.

### Key CSS Selectors

| Selector | Description |
|----------|-------------|
| `.ts-wrapper .ts-control` | Main control (looks like an input) |
| `.ts-wrapper.focus .ts-control` | Focused state (primary ring) |
| `.ts-wrapper .ts-dropdown` | Dropdown panel |
| `.ts-dropdown .option` | Option row |
| `.ts-dropdown .option.selected` | Currently selected option |
| `.ts-wrapper.multi .ts-control > .item` | Multi-select tag chip |
| `.ts-dropdown .no-results` | "No results" message |

### Design Tokens Used

- Border: `border-neutral-100` (default), `border-primary` (focused)
- Background: `bg-neutral-0`
- Text: `text-neutral-900` (control), `text-neutral-600` (options)
- Selected: `bg-primary/10 text-primary`
- Hover: `bg-neutral-50 text-neutral-900`
- Focus ring: `ring-primary/10 ring-4`
- Border radius: `rounded-xl`
- Min height: `48px` (matches other form inputs)

### Overriding Styles

```css
/* Make dropdown taller */
.ts-dropdown .ts-dropdown-content {
    @apply max-h-80; /* default is max-h-60 */
}

/* Change tag colors */
.ts-wrapper.multi .ts-control > .item {
    @apply bg-success/10 border-success/20 text-success;
}
```

---

## Dark Mode & RTL

Both are handled automatically:

- **Dark mode**: CSS uses semantic tokens (`bg-neutral-0`, `border-neutral-100`) that adapt to `.dark` class on `<html>`
- **RTL**: Dropdown arrow flips via `[dir="rtl"]` rules. On RTL toggle, the `rtl-toggled` event re-initializes Tom Select

---

## Troubleshooting

### Tom Select not initializing

- Ensure the `<select>` has class `ts-basic` or `ts-multi` (use `<x-forms.tom-select>`)
- Check browser console for JS errors
- If the select is added dynamically, dispatch `datatable:updated` event

### Double dropdown arrows

- Do NOT add `ts-basic` to `<x-forms.select>` — it applies `select-field` CSS which has its own arrow
- Always use `<x-forms.tom-select>` for searchable selects

### Duplicate initialization

The init script checks `if (el.tomselect) return` to skip already-initialized elements. Don't manually initialize AND use `ts-basic` on the same element.

### Select value not submitting

- Ensure `name` attribute is on the `<select>` element
- For multi-select, name must end with `[]` (e.g., `name="tags[]"`)

### Dropdown behind other elements

```css
.ts-wrapper .ts-dropdown {
    @apply z-[100]; /* increase from default z-50 */
}
```
