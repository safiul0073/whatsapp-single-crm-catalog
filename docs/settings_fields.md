# Settings Field Types

Reference for all available field types when defining settings in config files (e.g. `config/settings.php`).

---

## Config Structure

```php
return [
    'group_key' => [
        'label'       => 'Group Label',        // Sidebar tab label
        'icon'        => 'ph ph-gear',          // Phosphor icon class
        'description' => 'Group description',   // Shown below the section title
        'settings'    => [
            // ... fields go here
        ],
    ],
];
```

---

## Common Field Properties

Every field supports these properties:

| Property           | Required | Description                                                |
|--------------------|----------|------------------------------------------------------------|
| `type`             | Yes      | Field type (see below)                                     |
| `label`            | Yes      | Display label                                              |
| `hint`             | No       | Help text shown below the label                            |
| `default`          | No       | Default value when no DB override exists                   |
| `rules`            | No       | Laravel validation rules string                            |
| `public`           | No       | If `true`, included in `getPublicSettings()` (for frontend/API) |

---

## Field Types

### `text`

Standard single-line text input.

```php
'site_name' => [
    'type'    => 'text',
    'label'   => 'Site Name',
    'hint'    => 'The name displayed across the platform',
    'default' => 'Admin Panel',
    'rules'   => 'required|string|max:255',
    'public'  => true,
],
```

**Stored as:** string | **Cast to:** string

---

### `textarea`

Multi-line text input (renders 3 rows by default).

```php
'site_description' => [
    'type'    => 'textarea',
    'label'   => 'Site Description',
    'hint'    => 'A brief description of your platform',
    'default' => '',
    'rules'   => 'nullable|string|max:500',
],
```

**Stored as:** string | **Cast to:** string

---

### `email`

Email input with browser-level email validation.

```php
'contact_email' => [
    'type'    => 'email',
    'label'   => 'Contact Email',
    'hint'    => 'Primary contact email address',
    'default' => 'admin@example.com',
    'rules'   => 'required|email|max:255',
],
```

**Stored as:** string | **Cast to:** string

---

### `number`

Numeric input field.

```php
'items_per_page' => [
    'type'    => 'number',
    'label'   => 'Items Per Page',
    'hint'    => 'Default pagination size for lists and tables',
    'default' => 15,
    'rules'   => 'required|integer|min:5|max:100',
],
```

**Stored as:** string | **Cast to:** `int`

---

### `select`

Dropdown select. Automatically uses **Tom Select** (searchable) when options exceed 10 items or when `options_resolver` is set. Otherwise renders a native styled select.

#### Static options

```php
'date_format' => [
    'type'    => 'select',
    'label'   => 'Date Format',
    'hint'    => 'How dates are displayed across the platform',
    'default' => 'd M, Y',
    'rules'   => 'required|string',
    'options' => [
        'd M, Y' => '23 Feb, 2026',
        'M d, Y' => 'Feb 23, 2026',
        'Y-m-d'  => '2026-02-23',
        'd/m/Y'  => '23/02/2026',
        'm/d/Y'  => '02/23/2026',
    ],
],
```

#### Dynamic options (options_resolver)

```php
'default_timezone' => [
    'type'             => 'select',
    'label'            => 'Default Timezone',
    'hint'             => 'Timezone used for dates and scheduling',
    'default'          => 'UTC',
    'rules'            => 'required|timezone',
    'options_resolver' => 'timezones',   // Built-in resolver, uses timezone_identifiers_list()
],
```

**Extra properties:**

| Property           | Description                                              |
|--------------------|----------------------------------------------------------|
| `options`          | `['value' => 'Display Label']` key-value pairs           |
| `options_resolver` | Dynamic option source. Currently supports: `'timezones'` |

**Stored as:** string | **Cast to:** string

---

### `boolean`

Toggle switch for on/off values. Renders inline within the regular settings rows.

```php
'show_footer' => [
    'type'    => 'boolean',
    'label'   => 'Show Footer',
    'hint'    => 'Display footer section on public pages',
    'default' => true,
    'rules'   => 'nullable|boolean',
],
```

**Stored as:** `'1'` or `'0'` | **Cast to:** `bool`

---

### `feature`

Feature flag toggle. Renders as a **styled card/tile** with animated knob, status label (Enabled/Disabled), and color-coded strip. Feature fields are grouped separately from regular fields and displayed in a 2-column grid below the regular settings.

```php
'enable_registration' => [
    'type'    => 'feature',
    'label'   => 'User Registration',
    'hint'    => 'Allow new users to create accounts',
    'default' => true,
    'public'  => true,
],
```

**Stored as:** `'1'` or `'0'` | **Cast to:** `bool`

> **`boolean` vs `feature`:** Both store/cast the same way. Use `boolean` for simple inline toggles within regular settings rows. Use `feature` for prominent feature flags that deserve visual emphasis as styled cards.

---

### `media`

Media picker that opens the media library modal. Stores the **media record ID** (integer).

```php
'site_logo' => [
    'type'    => 'media',
    'label'   => 'Site Logo',
    'hint'    => 'Recommended: 200x50px, PNG or SVG',
    'default' => null,
    'accept'  => 'image',
    'rules'   => 'nullable|integer',
],
```

**Extra properties:**

| Property | Description                              |
|----------|------------------------------------------|
| `accept` | `'image'` or `'file'` (default: `image`) |

**Stored as:** string (ID) | **Cast to:** string

Use the `media_url($id)` helper to get the public URL from a stored media ID.

---

### `color`

Color picker with a visual swatch and hex text input. The swatch and text field stay in sync.

```php
'primary_color' => [
    'type'    => 'color',
    'label'   => 'Primary Color',
    'hint'    => 'Main brand color for buttons, links, and accents',
    'default' => '#5096f2',
    'rules'   => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
    'public'  => true,
],
```

**Stored as:** string (hex like `#5096f2`) | **Cast to:** string

---

### `checkbox`

Multiple-choice checkboxes. Renders a grid of checkboxes using the `<x-forms.checkbox-group>` component. Values are stored as a comma-separated string and cast to an array.

```php
'allowed_file_types' => [
    'type'    => 'checkbox',
    'label'   => 'Allowed File Types',
    'hint'    => 'Select which file types users can upload',
    'default' => 'jpg,png,pdf',
    'rules'   => 'nullable|array',
    'options' => [
        'jpg'  => 'JPEG Images',
        'png'  => 'PNG Images',
        'gif'  => 'GIF Images',
        'pdf'  => 'PDF Documents',
        'docx' => 'Word Documents',
        'xlsx' => 'Excel Spreadsheets',
    ],
    'columns' => 3,
],
```

**Extra properties:**

| Property  | Description                                         |
|-----------|-----------------------------------------------------|
| `options` | `['value' => 'Display Label']` key-value pairs      |
| `columns` | Grid columns for layout (default: `2`)              |

**Stored as:** comma-separated string (e.g. `jpg,png,pdf`) | **Cast to:** `array`

**Accessing values:**
```php
$types = setting('allowed_file_types');        // ['jpg', 'png', 'pdf']
in_array('pdf', setting('allowed_file_types')) // true
```

---

### `tags`

Multi-select with searchable tag-style input. Uses **Tom Select** with `remove_button` plugin. Values are stored as a comma-separated string and cast to an array.

```php
'supported_currencies' => [
    'type'    => 'tags',
    'label'   => 'Supported Currencies',
    'hint'    => 'Select currencies available for transactions',
    'default' => 'USD,EUR',
    'rules'   => 'nullable|array',
    'options' => [
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'GBP' => 'British Pound',
        'JPY' => 'Japanese Yen',
        'CAD' => 'Canadian Dollar',
        'AUD' => 'Australian Dollar',
    ],
],
```

**Extra properties:**

| Property  | Description                                    |
|-----------|------------------------------------------------|
| `options` | `['value' => 'Display Label']` key-value pairs |

**Stored as:** comma-separated string (e.g. `USD,EUR,GBP`) | **Cast to:** `array`

> **`checkbox` vs `tags`:** Both store arrays. Use `checkbox` when you have a small, fixed set of options that benefit from seeing all choices at once. Use `tags` for larger option lists where searchable dropdown selection with removable tags is more ergonomic.

---

### `date`

Date picker powered by **Air Datepicker**. Opens a calendar popup with auto-close on selection.

```php
'launch_date' => [
    'type'    => 'date',
    'label'   => 'Launch Date',
    'hint'    => 'When the site goes live',
    'default' => '',
    'rules'   => 'nullable|string',
],
```

**Stored as:** string (e.g. `03/15/2026`) | **Cast to:** string

---

### `date_range`

Date range picker. Allows selecting a start and end date with a separator.

```php
'promo_period' => [
    'type'    => 'date_range',
    'label'   => 'Promotion Period',
    'hint'    => 'Start and end dates for the active promotion',
    'default' => '',
    'rules'   => 'nullable|string',
],
```

**Stored as:** string (e.g. `03/01/2026 - 03/31/2026`) | **Cast to:** string

---

### `datetime`

Combined date and time picker. Calendar with time sliders below.

```php
'scheduled_maintenance' => [
    'type'    => 'datetime',
    'label'   => 'Scheduled Maintenance',
    'hint'    => 'Next planned maintenance window',
    'default' => '',
    'rules'   => 'nullable|string',
],
```

**Stored as:** string (e.g. `03/15/2026 14:30`) | **Cast to:** string

---

### `time`

Time-only picker with hour/minute sliders. No calendar.

```php
'business_hours_start' => [
    'type'    => 'time',
    'label'   => 'Business Hours Start',
    'hint'    => 'When support becomes available',
    'default' => '09:00',
    'rules'   => 'nullable|string',
],
```

**Stored as:** string (e.g. `09:00`) | **Cast to:** string

---

## Complete Example

A config file with all field types:

```php
<?php

return [

    'general' => [
        'label'       => 'General',
        'icon'        => 'ph ph-gear',
        'description' => 'Core configuration',
        'settings'    => [
            'site_name' => [
                'type'    => 'text',
                'label'   => 'Site Name',
                'default' => 'My App',
                'rules'   => 'required|string|max:255',
                'public'  => true,
            ],
            'site_description' => [
                'type'    => 'textarea',
                'label'   => 'Site Description',
                'default' => '',
                'rules'   => 'nullable|string|max:500',
            ],
            'admin_email' => [
                'type'    => 'email',
                'label'   => 'Admin Email',
                'default' => 'admin@example.com',
                'rules'   => 'required|email|max:255',
            ],
            'items_per_page' => [
                'type'    => 'number',
                'label'   => 'Items Per Page',
                'default' => 15,
                'rules'   => 'required|integer|min:5|max:100',
            ],
            'date_format' => [
                'type'    => 'select',
                'label'   => 'Date Format',
                'default' => 'd M, Y',
                'rules'   => 'required|string',
                'options' => [
                    'd M, Y' => '23 Feb, 2026',
                    'Y-m-d'  => '2026-02-23',
                ],
            ],
            'show_sidebar' => [
                'type'    => 'boolean',
                'label'   => 'Show Sidebar',
                'hint'    => 'Toggle sidebar visibility',
                'default' => true,
            ],
            'allowed_file_types' => [
                'type'    => 'checkbox',
                'label'   => 'Allowed File Types',
                'default' => 'jpg,png,pdf',
                'rules'   => 'nullable|array',
                'options' => [
                    'jpg'  => 'JPEG Images',
                    'png'  => 'PNG Images',
                    'pdf'  => 'PDF Documents',
                ],
                'columns' => 3,
            ],
            'supported_languages' => [
                'type'    => 'tags',
                'label'   => 'Supported Languages',
                'default' => 'en,fr',
                'rules'   => 'nullable|array',
                'options' => [
                    'en' => 'English',
                    'fr' => 'French',
                    'es' => 'Spanish',
                    'de' => 'German',
                    'ar' => 'Arabic',
                ],
            ],
        ],
    ],

    'appearance' => [
        'label'       => 'Appearance',
        'icon'        => 'ph ph-paint-brush',
        'description' => 'Branding and visuals',
        'settings'    => [
            'logo' => [
                'type'    => 'media',
                'label'   => 'Logo',
                'hint'    => 'Recommended: 200x50px',
                'default' => null,
                'accept'  => 'image',
                'rules'   => 'nullable|integer',
            ],
            'primary_color' => [
                'type'    => 'color',
                'label'   => 'Primary Color',
                'default' => '#5096f2',
                'rules'   => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'public'  => true,
            ],
        ],
    ],

    'schedule' => [
        'label'       => 'Schedule',
        'icon'        => 'ph ph-calendar',
        'description' => 'Date and time settings',
        'settings'    => [
            'launch_date' => [
                'type'    => 'date',
                'label'   => 'Launch Date',
                'default' => '',
                'rules'   => 'nullable|string',
            ],
            'promo_period' => [
                'type'    => 'date_range',
                'label'   => 'Promotion Period',
                'default' => '',
                'rules'   => 'nullable|string',
            ],
            'next_maintenance' => [
                'type'    => 'datetime',
                'label'   => 'Next Maintenance',
                'default' => '',
                'rules'   => 'nullable|string',
            ],
            'business_hours_start' => [
                'type'    => 'time',
                'label'   => 'Business Hours Start',
                'default' => '09:00',
                'rules'   => 'nullable|string',
            ],
        ],
    ],

    'features' => [
        'label'       => 'Features',
        'icon'        => 'ph ph-toggle-right',
        'description' => 'Feature flags and capabilities',
        'settings'    => [
            'enable_registration' => [
                'type'    => 'feature',
                'label'   => 'User Registration',
                'hint'    => 'Allow new users to create accounts',
                'default' => true,
                'public'  => true,
            ],
            'maintenance_mode' => [
                'type'    => 'feature',
                'label'   => 'Maintenance Mode',
                'hint'    => 'Show maintenance page to non-admin users',
                'default' => false,
            ],
        ],
    ],

];
```

---

## Accessing Settings

Each settings module auto-generates a global helper function:

```php
// Settings module → setting()
setting('site_name');
setting('date_format', 'd M, Y');   // with fallback

// HomePageSettings module → home_page_setting()
home_page_setting('hero_title');

// PaymentSettings module → payment_setting()
payment_setting('stripe_key');
```

Or inject the service directly:

```php
use App\Modules\Settings\Services\SettingsService;

$service = app(SettingsService::class);
$value = $service->get('site_name');
$service->set('site_name', 'New Name');
```
