# Dashboard Widgets

A modular widget system for building dashboard pages. Widgets are self-contained components that render data cards, charts, tables, and any custom content. Each module can register its own widgets, and the dashboard automatically arranges them in a responsive grid.

---

## Table of Contents

- [Overview](#overview)
- [How It Works](#how-it-works)
- [Quick Start: Creating a Widget](#quick-start-creating-a-widget)
  - [Step 1: Generate the Widget](#step-1-generate-the-widget)
  - [Step 2: Add Your Data](#step-2-add-your-data)
  - [Step 3: Build the View](#step-3-build-the-view)
- [Quick Start: Creating a Chart Widget](#quick-start-creating-a-chart-widget)
  - [Step 1: Generate the Chart Widget](#step-1-generate-the-chart-widget)
  - [Step 2: Add Your Data Query](#step-2-add-your-data-query)
- [Widget Types](#widget-types)
  - [BaseWidget (Content Widget)](#basewidget-content-widget)
  - [ChartWidget (ApexCharts)](#chartwidget-apexcharts)
- [Widget Properties](#widget-properties)
  - [Required Methods](#required-methods)
  - [Optional Overrides (BaseWidget Defaults)](#optional-overrides-basewidget-defaults)
- [Width and Layout](#width-and-layout)
- [Chart Types and Data Formats](#chart-types-and-data-formats)
  - [Area Chart](#area-chart)
  - [Line Chart](#line-chart)
  - [Bar Chart](#bar-chart)
  - [Donut Chart](#donut-chart)
  - [Pie Chart](#pie-chart)
  - [Radial Bar Chart](#radial-bar-chart)
  - [Chart Customization](#chart-customization)
- [Permissions](#permissions)
- [Conditional Rendering](#conditional-rendering)
- [Caching](#caching)
- [Injecting Dependencies](#injecting-dependencies)
- [Registering Widgets Manually](#registering-widgets-manually)
- [Panels: Admin vs User](#panels-admin-vs-user)
- [View Patterns](#view-patterns)
  - [KPI Stats Card](#kpi-stats-card)
  - [List Widget](#list-widget)
  - [List with Action Link](#list-with-action-link)
  - [List with Status Badges](#list-with-status-badges)
- [Artisan Command Reference](#artisan-command-reference)
- [Full Example: Recent Orders Widget](#full-example-recent-orders-widget)
- [Full Example: Revenue Chart Widget](#full-example-revenue-chart-widget)
- [Architecture Overview](#architecture-overview)
- [Existing Widgets Reference](#existing-widgets-reference)

---

## Overview

Each widget is a PHP class that:
- Fetches its own data
- Renders its own Blade view (or uses the shared chart partial)
- Declares its position, width, target panel, and permission

The `WidgetRegistry` service collects all widgets, filters them by panel and permission, sorts by position, and hands them to the dashboard view for rendering.

---

## How It Works

```
Module ServiceProvider registers widget
        |
        v
WidgetRegistry stores widget instance
        |
        v
Dashboard Controller calls getForPanel('admin', $user)
        |
        v
Registry filters: panel match → permission check → shouldRender()
        |
        v
Registry sorts by position (lower number = first)
        |
        v
Dashboard view groups widgets by width and renders them
        |
        v
Full-width widgets render alone
Half/quarter widgets render in a 2-column grid
```

---

## Quick Start: Creating a Widget

### Step 1: Generate the Widget

```bash
php artisan make:widget RecentOrders --module=Orders --width=half --position=30
```

This creates:
- **Widget class:** `app/Modules/Orders/Widgets/RecentOrdersWidget.php`
- **Blade view:** `resources/views/widgets/admin/recent-orders.blade.php`
- **Auto-registers** the widget in the module's ServiceProvider

### Step 2: Add Your Data

Edit `app/Modules/Orders/Widgets/RecentOrdersWidget.php`:

```php
<?php

namespace App\Modules\Orders\Widgets;

use App\Modules\Orders\Models\Order;
use App\Modules\Shared\Widgets\BaseWidget;

class RecentOrdersWidget extends BaseWidget
{
    public function id(): string
    {
        return 'admin-recent-orders';
    }

    public function title(): string
    {
        return __('Recent Orders');
    }

    public function render(): string
    {
        $orders = Order::with('user')
            ->latest()
            ->limit(5)
            ->get();

        return $this->view('widgets.admin.recent-orders', compact('orders'));
    }

    public function position(): int
    {
        return 30;
    }

    public function width(): string
    {
        return 'half';
    }
}
```

### Step 3: Build the View

Edit `resources/views/widgets/admin/recent-orders.blade.php`:

```blade
<div class="section-card">
    <h2 class="heading-5 text-neutral-950 mb-4">{{ __('Recent Orders') }}</h2>

    @if($orders->isNotEmpty())
        <div class="space-y-3">
            @foreach($orders as $order)
            <div class="flex items-center gap-3 rounded-xl border border-neutral-100 bg-neutral-0 p-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <i class="ph ph-package"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-neutral-900 truncate">
                        Order #{{ $order->id }} — {{ $order->user->name }}
                    </p>
                    <p class="text-xs text-neutral-400">{{ $order->created_at->diffForHumans() }}</p>
                </div>
                <div class="shrink-0">
                    <x-ui.badge :variant="$order->status === 'completed' ? 'success' : 'warning'">
                        {{ ucfirst($order->status) }}
                    </x-ui.badge>
                </div>
            </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-neutral-400">{{ __('No recent orders.') }}</p>
    @endif
</div>
```

Done. The widget appears on the admin dashboard automatically.

---

## Quick Start: Creating a Chart Widget

### Step 1: Generate the Chart Widget

```bash
php artisan make:widget MonthlyRevenue --module=Orders --chart --chart-type=bar --width=half --position=35
```

This creates the widget class only (chart widgets use a shared Blade partial — no separate view file).

### Step 2: Add Your Data Query

Edit `app/Modules/Orders/Widgets/MonthlyRevenueWidget.php`:

```php
<?php

namespace App\Modules\Orders\Widgets;

use App\Modules\Orders\Models\Order;
use App\Modules\Shared\Widgets\ChartWidget;

class MonthlyRevenueWidget extends ChartWidget
{
    public function id(): string
    {
        return 'admin-monthly-revenue';
    }

    public function title(): string
    {
        return __('Monthly Revenue');
    }

    public function chartType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        // Query your data however you need
        $months = collect(range(1, 6))->map(function ($i) {
            $date = now()->subMonths(6 - $i);
            return [
                'label' => $date->format('M'),
                'total' => Order::whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->sum('total'),
            ];
        });

        return [
            'series' => [
                ['name' => 'Revenue', 'data' => $months->pluck('total')->toArray()],
            ],
            'categories' => $months->pluck('label')->toArray(),
        ];
    }

    public function position(): int
    {
        return 35;
    }

    public function width(): string
    {
        return 'half';
    }

    public function cacheFor(): ?int
    {
        return 300; // Cache for 5 minutes
    }
}
```

Done. The chart renders automatically using ApexCharts with dark mode support.

---

## Widget Types

### BaseWidget (Content Widget)

For any custom HTML content: stats cards, tables, lists, forms, etc.

```php
use App\Modules\Shared\Widgets\BaseWidget;

class MyWidget extends BaseWidget
{
    // ... implement required methods

    public function render(): string
    {
        $data = [/* your data */];
        return $this->view('widgets.admin.my-widget', compact('data'));
    }
}
```

You provide a Blade view and the widget renders it.

### ChartWidget (ApexCharts)

For data visualizations. Renders automatically using the shared chart partial — no Blade view needed.

```php
use App\Modules\Shared\Widgets\ChartWidget;

class MyChartWidget extends ChartWidget
{
    // ... implement required methods

    public function chartType(): string
    {
        return 'area'; // area, line, bar, donut, pie, radialBar
    }

    protected function getData(): array
    {
        return [
            'series' => [['name' => 'Sales', 'data' => [10, 20, 30]]],
            'categories' => ['Jan', 'Feb', 'Mar'],
        ];
    }
}
```

Charts handle dark mode, tooltips, legends, and responsive sizing automatically.

---

## Widget Properties

### Required Methods

Every widget must implement these methods (from the `DashboardWidget` interface):

| Method | Return Type | Description |
|--------|-------------|-------------|
| `id()` | `string` | Unique identifier (e.g., `'admin-recent-orders'`) |
| `title()` | `string` | Display title shown in the widget header |
| `render()` | `string` | Returns rendered HTML (BaseWidget) or handled by ChartWidget |
| `position()` | `int` | Sort order — lower numbers render first |

### Optional Overrides (BaseWidget Defaults)

These have sensible defaults. Override only when needed:

| Method | Return Type | Default | Description |
|--------|-------------|---------|-------------|
| `width()` | `string` | `'half'` | Layout width: `'full'`, `'half'`, or `'quarter'` |
| `permission()` | `?string` | `null` | Required permission (null = visible to all) |
| `panel()` | `string` | `'admin'` | Target panel: `'admin'`, `'user'`, or `'all'` |
| `shouldRender()` | `bool` | `true` | Return `false` to hide the widget conditionally |
| `cacheFor()` | `?int` | `null` | Cache duration in seconds (null = no caching) |

**ChartWidget adds these optional overrides:**

| Method | Return Type | Default | Description |
|--------|-------------|---------|-------------|
| `chartType()` | `string` | `'area'` | ApexCharts type |
| `chartHeight()` | `int` | `300` | Chart height in pixels |
| `chartColors()` | `array` | `['#5096f2', '#6366f1', ...]` | Color palette |
| `getChartOptions()` | `array` | `[]` | Additional ApexCharts options to merge |

---

## Width and Layout

The dashboard view groups widgets by width and arranges them in a responsive grid:

| Width | Behavior | Use For |
|-------|----------|---------|
| `'full'` | Takes the entire row | KPI stats row, full-width charts, tables |
| `'half'` | Two per row on desktop, stacked on mobile | Most widgets — lists, charts, cards |
| `'quarter'` | Treated as half (2-column grid) | Reserved for future 4-column layouts |

**Grid layout logic:**

```
[full-width widget]           ← renders alone, full row
[half] [half]                 ← grouped into 2-column grid
[full-width widget]           ← renders alone, breaks the grid
[half] [half] [half]          ← grouped, 2 cols on desktop (third wraps)
```

**Position numbers determine the order.** Use multiples of 5 or 10 to leave room for future widgets:

| Range | Typical Use |
|-------|-------------|
| 10-19 | KPI stats, hero content |
| 20-29 | Primary data widgets |
| 30-39 | Secondary data widgets |
| 40-49 | Supporting widgets |
| 50-60 | System info, low-priority |

---

## Chart Types and Data Formats

ChartWidget supports all major ApexCharts types. The `getData()` method must return an array matching the chart type's expected format.

### Area Chart

Smooth filled line chart. Great for trends over time.

```php
public function chartType(): string { return 'area'; }

protected function getData(): array
{
    return [
        'series' => [
            ['name' => 'Revenue', 'data' => [4200, 5100, 3800, 6200, 5800, 7100]],
            ['name' => 'Expenses', 'data' => [2800, 3200, 2900, 3800, 3500, 4200]],
        ],
        'categories' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
    ];
}
```

### Line Chart

Simple line chart without fill. Good for comparing multiple series.

```php
public function chartType(): string { return 'line'; }

// Same data format as area chart
```

### Bar Chart

Vertical bar chart. Good for comparisons across categories.

```php
public function chartType(): string { return 'bar'; }

// Same data format as area/line chart
```

### Donut Chart

Ring chart showing proportions. Good for distribution breakdowns.

```php
public function chartType(): string { return 'donut'; }

protected function getData(): array
{
    return [
        'series' => [42, 28, 18, 12],
        'labels' => ['Admin', 'Editor', 'User', 'Guest'],
    ];
}
```

### Pie Chart

Full circle chart. Same data format as donut.

```php
public function chartType(): string { return 'pie'; }

// Same data format as donut chart
```

### Radial Bar Chart

Circular progress bars. Good for goal tracking.

```php
public function chartType(): string { return 'radialBar'; }

protected function getData(): array
{
    return [
        'series' => [75, 60, 45],
        'labels' => ['Target A', 'Target B', 'Target C'],
    ];
}
```

### Chart Customization

Override `chartHeight()`, `chartColors()`, or `getChartOptions()` for additional control:

```php
public function chartHeight(): int
{
    return 260; // Shorter chart
}

public function chartColors(): array
{
    return ['#22c55e', '#ef4444']; // Custom green/red palette
}

protected function getChartOptions(): array
{
    // Any valid ApexCharts option — merged into the chart config
    return [
        'plotOptions' => [
            'bar' => ['horizontal' => true],
        ],
    ];
}
```

See the [ApexCharts documentation](https://apexcharts.com/docs) for all available options.

---

## Permissions

Widgets can require a permission to be visible. If the user lacks the permission, the widget is hidden automatically.

```php
public function permission(): ?string
{
    return 'orders.view'; // Only users with this permission see the widget
}
```

Return `null` (default) to make the widget visible to all authenticated users.

Permission is checked via `$user->can()` using Spatie Laravel Permission.

---

## Conditional Rendering

Use `shouldRender()` to hide a widget when there's no data to show:

```php
public function shouldRender(): bool
{
    return Order::where('status', 'pending')->exists();
}
```

This prevents empty widgets from cluttering the dashboard. The widget is filtered out before rendering — no empty card is shown.

---

## Caching

Cache expensive widget output to improve dashboard load time:

```php
public function cacheFor(): ?int
{
    return 300; // Cache rendered HTML for 5 minutes
}
```

- **Cache key:** `widget:{widget_id}` (e.g., `widget:admin-user-distribution`)
- **Return `null`** (default) to disable caching
- Cache is stored using Laravel's default cache driver
- The entire rendered HTML is cached, not the data

**When to cache:**
- Widgets with expensive database queries
- Widgets that don't need real-time data
- Chart widgets with aggregation queries

**When NOT to cache:**
- Widgets showing real-time data
- Widgets with user-specific content
- Widgets with very fast queries

---

## Injecting Dependencies

Widgets can accept constructor dependencies. Inject them when registering the widget in your ServiceProvider:

```php
// Widget class
class StatsWidget extends BaseWidget
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function render(): string
    {
        $stats = $this->dashboardService->getAdminStats();
        return $this->view('widgets.admin.stats', compact('stats'));
    }
}
```

```php
// ServiceProvider boot()
$registry = $this->app->make(WidgetRegistry::class);
$dashboardService = $this->app->make(DashboardService::class);

$registry->register(new StatsWidget($dashboardService));
```

For widgets without dependencies, just instantiate directly:

```php
$registry->register(new RecentProductsWidget);
```

---

## Registering Widgets Manually

The `make:widget` command auto-registers widgets. If you need to register manually, add this to your module's ServiceProvider `boot()` method:

```php
use App\Modules\Orders\Widgets\RecentOrdersWidget;
use App\Services\WidgetRegistry;

public function boot(): void
{
    if ($this->app->bound(WidgetRegistry::class)) {
        $this->app->make(WidgetRegistry::class)->register(new RecentOrdersWidget);
    }
}
```

The `$this->app->bound()` check ensures the widget registration doesn't fail if the registry hasn't been initialized (e.g., during testing).

---

## Panels: Admin vs User

Widgets target a specific panel (or all panels):

```php
public function panel(): string
{
    return 'admin'; // Only appears on admin dashboard
}
```

| Value | Dashboard |
|-------|-----------|
| `'admin'` | Admin panel dashboard (`/admin`) |
| `'user'` | User panel dashboard (`/dashboard`) |
| `'all'` | Both dashboards |

Each dashboard controller loads only its panel's widgets:

```php
// Admin DashboardController
$widgets = $this->widgetRegistry->getForPanel('admin', auth()->user());

// User DashboardController
$widgets = $this->widgetRegistry->getForPanel('user', auth()->user());
```

---

## View Patterns

Here are common Blade patterns used in existing widgets. Use these as starting points for your widget views.

### KPI Stats Card

A row of stat cards using the `<x-ui.kpi-card>` component:

```blade
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-ui.kpi-card
        :title="__('Total Orders')"
        :value="$stats['total_orders']"
        icon="ph-package"
        color="primary"
    />
    <x-ui.kpi-card
        :title="__('Revenue')"
        :value="'$' . number_format($stats['revenue'])"
        icon="ph-currency-dollar"
        color="success"
    />
    <x-ui.kpi-card
        :title="__('Pending')"
        :value="$stats['pending']"
        icon="ph-clock"
        color="warning"
    />
    <x-ui.kpi-card
        :title="__('Cancelled')"
        :value="$stats['cancelled']"
        icon="ph-x-circle"
        color="error"
    />
</div>
```

**KPI card colors:** `primary`, `success`, `warning`, `error`
**Icons:** Any [Phosphor icon](https://phosphoricons.com/) class (e.g., `ph-users`, `ph-chart-line`)

Set `width()` to `'full'` for a stats row.

### List Widget

A simple list of items with icons:

```blade
<div class="section-card">
    <h2 class="heading-5 text-neutral-950 mb-4">{{ __('Recent Products') }}</h2>

    @if($products->isNotEmpty())
        <div class="space-y-3">
            @foreach($products as $product)
            <div class="flex items-center gap-3 rounded-xl border border-neutral-100 bg-neutral-0 p-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <i class="ph ph-package"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-neutral-900 truncate">{{ $product->name }}</p>
                    <p class="text-xs text-neutral-400">{{ $product->created_at->diffForHumans() }}</p>
                </div>
            </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-neutral-400">{{ __('No recent products.') }}</p>
    @endif
</div>
```

### List with Action Link

Add a "View All" link in the header:

```blade
<div class="section-card">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="heading-5 text-neutral-950">{{ __('Login Activity') }}</h2>
        <a href="{{ route('admin.login-activity.index') }}" class="text-sm text-primary hover:underline">
            {{ __('View All') }}
        </a>
    </div>

    {{-- List items here --}}
</div>
```

### List with Status Badges

Add color-coded badges and icons based on status:

```blade
<div class="flex items-center gap-3 rounded-xl border border-neutral-100 bg-neutral-0 p-3">
    <div class="flex h-8 w-8 items-center justify-center rounded-lg
        {{ $item->status === 'active' ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }}
    ">
        <i class="ph {{ $item->status === 'active' ? 'ph-check-circle' : 'ph-x-circle' }}"></i>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-neutral-900 truncate">{{ $item->name }}</p>
        <p class="text-xs text-neutral-400">{{ $item->created_at->diffForHumans() }}</p>
    </div>
    <div class="shrink-0">
        <x-ui.badge :variant="$item->status === 'active' ? 'success' : 'danger'">
            {{ ucfirst($item->status) }}
        </x-ui.badge>
    </div>
</div>
```

---

## Artisan Command Reference

```bash
php artisan make:widget {name}
    {--module=Shared}         # Module to place widget in
    {--panel=admin}           # Target panel: admin, user, all
    {--width=half}            # Widget width: full, half, quarter
    {--position=50}           # Sort position (lower = first)
    {--chart}                 # Create a ChartWidget instead of BaseWidget
    {--chart-type=area}       # Chart type: area, line, bar, donut, pie, radialBar
```

**Examples:**

```bash
# Simple content widget in your module
php artisan make:widget RecentOrders --module=Orders --position=30

# Full-width stats widget
php artisan make:widget OrderStats --module=Orders --width=full --position=10

# Bar chart for the user dashboard
php artisan make:widget SpendingChart --module=Orders --chart --chart-type=bar --panel=user --position=20

# Donut chart with caching (add cacheFor() manually after generation)
php artisan make:widget CategoryBreakdown --module=Products --chart --chart-type=donut --position=40

# Widget visible on both panels
php artisan make:widget Announcements --module=Shared --panel=all --width=full --position=5
```

**What the command creates:**

| Flag | Creates |
|------|---------|
| (default) | Widget class + Blade view |
| `--chart` | Widget class only (uses shared chart partial) |

The command also auto-registers the widget in the module's ServiceProvider `boot()` method.

### Removing a Widget

```bash
php artisan remove:widget {name}
    {--module=}           # Module (auto-detected if omitted)
    {--force}             # Skip confirmation
```

**Examples:**

```bash
# Interactive removal (auto-detects module)
php artisan remove:widget RecentOrders

# Specify the module explicitly
php artisan remove:widget RecentOrders --module=Orders

# Skip confirmation
php artisan remove:widget RecentOrders --force
```

**What it removes:**
- Widget class file from `app/Modules/{Module}/Widgets/`
- Blade view file from `resources/views/widgets/{panel}/` (content widgets only)
- Registration line and use statement from the module's ServiceProvider
- Empty `Widgets/` directory if no other widgets remain

**What it warns about:**
- Any files outside the ServiceProvider that reference the widget class (you fix these manually)

---

## Full Example: Recent Orders Widget

A complete content widget showing the latest orders with status badges.

**Generate:**

```bash
php artisan make:widget RecentOrders --module=Orders --width=half --position=30
```

**Widget class** (`app/Modules/Orders/Widgets/RecentOrdersWidget.php`):

```php
<?php

namespace App\Modules\Orders\Widgets;

use App\Modules\Orders\Models\Order;
use App\Modules\Shared\Widgets\BaseWidget;

class RecentOrdersWidget extends BaseWidget
{
    public function id(): string
    {
        return 'admin-recent-orders';
    }

    public function title(): string
    {
        return __('Recent Orders');
    }

    public function render(): string
    {
        $orders = Order::with('user')->latest()->limit(5)->get();

        return $this->view('widgets.admin.recent-orders', compact('orders'));
    }

    public function position(): int
    {
        return 30;
    }

    public function permission(): ?string
    {
        return 'orders.view';
    }

    public function shouldRender(): bool
    {
        return Order::exists();
    }
}
```

**Blade view** (`resources/views/widgets/admin/recent-orders.blade.php`):

```blade
<div class="section-card">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="heading-5 text-neutral-950">{{ __('Recent Orders') }}</h2>
        <a href="{{ route('admin.orders.index') }}" class="text-sm text-primary hover:underline">
            {{ __('View All') }}
        </a>
    </div>

    <div class="space-y-3">
        @foreach($orders as $order)
        <div class="flex items-center gap-3 rounded-xl border border-neutral-100 bg-neutral-0 p-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <i class="ph ph-package"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-neutral-900 truncate">
                    #{{ $order->id }} — {{ $order->user->name }}
                </p>
                <p class="text-xs text-neutral-400">${{ number_format($order->total, 2) }} &middot; {{ $order->created_at->diffForHumans() }}</p>
            </div>
            <div class="shrink-0">
                <x-ui.badge :variant="match($order->status) {
                    'completed' => 'success',
                    'pending' => 'warning',
                    'cancelled' => 'danger',
                    default => 'neutral',
                }">{{ ucfirst($order->status) }}</x-ui.badge>
            </div>
        </div>
        @endforeach
    </div>
</div>
```

---

## Full Example: Revenue Chart Widget

A chart widget showing monthly revenue as a bar chart with caching.

**Generate:**

```bash
php artisan make:widget MonthlyRevenue --module=Orders --chart --chart-type=bar --position=25
```

**Widget class** (`app/Modules/Orders/Widgets/MonthlyRevenueWidget.php`):

```php
<?php

namespace App\Modules\Orders\Widgets;

use App\Modules\Orders\Models\Order;
use App\Modules\Shared\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class MonthlyRevenueWidget extends ChartWidget
{
    public function id(): string
    {
        return 'admin-monthly-revenue';
    }

    public function title(): string
    {
        return __('Monthly Revenue');
    }

    public function chartType(): string
    {
        return 'bar';
    }

    public function chartHeight(): int
    {
        return 280;
    }

    public function chartColors(): array
    {
        return ['#22c55e']; // Green bars
    }

    protected function getData(): array
    {
        $months = collect(range(5, 0))->map(function ($i) {
            $date = now()->subMonths($i);
            return [
                'label' => $date->format('M'),
                'revenue' => Order::where('status', 'completed')
                    ->whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->sum('total'),
            ];
        });

        return [
            'series' => [
                ['name' => __('Revenue'), 'data' => $months->pluck('revenue')->toArray()],
            ],
            'categories' => $months->pluck('label')->toArray(),
        ];
    }

    public function position(): int
    {
        return 25;
    }

    public function width(): string
    {
        return 'half';
    }

    public function cacheFor(): ?int
    {
        return 600; // Cache for 10 minutes
    }
}
```

No Blade view needed — the chart renders automatically with dark mode support, tooltips, and responsive sizing.

---

## Architecture Overview

```
AppServiceProvider
    └── registers WidgetRegistry (singleton)

Module ServiceProviders (boot)
    └── register widgets into WidgetRegistry

DashboardController
    └── $registry->getForPanel('admin', $user)
            |
            ├── filter by panel
            ├── filter by permission
            ├── filter by shouldRender()
            └── sort by position()

Dashboard Blade View
    └── groups widgets by width
            |
            ├── full → renders alone
            └── half/quarter → 2-column grid
                    |
                    └── $registry->renderWidget($widget)
                            |
                            ├── cacheFor() ? Cache::remember() : render()
                            └── widget->render()
                                    |
                                    ├── BaseWidget → $this->view('blade.path', $data)
                                    └── ChartWidget → shared chart partial + ApexCharts
```

**Key files:**

| File | Purpose |
|------|---------|
| `app/Services/WidgetRegistry.php` | Central registry, filtering, caching |
| `app/Modules/Shared/Contracts/DashboardWidget.php` | Widget interface |
| `app/Modules/Shared/Widgets/BaseWidget.php` | Abstract base class with defaults |
| `app/Modules/Shared/Widgets/ChartWidget.php` | Chart base class (ApexCharts) |
| `app/Console/Commands/MakeWidgetCommand.php` | `make:widget` artisan command |
| `resources/views/panels/admin/dashboard.blade.php` | Admin dashboard layout |
| `resources/views/panels/user/dashboard.blade.php` | User dashboard layout |
| `resources/views/widgets/partials/chart.blade.php` | Shared chart rendering partial |
| `resources/views/widgets/admin/*.blade.php` | Admin widget views |
| `resources/views/widgets/user/*.blade.php` | User widget views |
| `stubs/widget/Widget.stub` | BaseWidget scaffold template |
| `stubs/widget/ChartWidget.stub` | ChartWidget scaffold template |
| `stubs/widget/view.blade.stub` | Widget view scaffold template |

---

## Existing Widgets Reference

### Admin Panel Widgets

| Widget | ID | Type | Width | Position | Module |
|--------|----|------|-------|----------|--------|
| Stats Overview | `admin-stats` | BaseWidget (KPI cards) | full | 10 | Shared |
| Sales Overview | `admin-sales-overview` | ChartWidget (area) | full | 15 | LoginActivity |
| Recent Activity | `admin-recent-activity` | BaseWidget (list) | half | 20 | Shared |
| Recent Products | `admin-recent-products` | BaseWidget (list) | half | 25 | Products |
| Login Activity | `admin-login-activity` | BaseWidget (list) | half | 30 | Shared |
| User Distribution | `admin-user-distribution` | ChartWidget (donut) | half | 35 | Shared |
| Recent Users | `admin-recent-users` | BaseWidget (list) | half | 40 | Shared |
| System Info | `admin-system-info` | BaseWidget (key-value) | half | 60 | Shared |

### User Panel Widgets

| Widget | ID | Type | Width | Position | Module |
|--------|----|------|-------|----------|--------|
| Welcome | `user-welcome` | BaseWidget | full | 10 | Shared |
| Quick Links | `user-quick-links` | BaseWidget | half | 20 | Shared |
