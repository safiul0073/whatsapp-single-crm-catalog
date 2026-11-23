@php
    $statusLabels = ['out' => __('Out of stock'), 'low' => __('Low stock'), 'in' => __('In stock')];
    $rowLabels = $statusLabels + ['saved' => __('Stock updated'), 'saveError' => __('Could not update stock')];
    $canManage = auth()->user()->can('commerce.products.manage');
    $tabs = [
        'all' => ['label' => __('All'), 'key' => 'total'],
        'out' => ['label' => __('Out of stock'), 'key' => 'out'],
        'low' => ['label' => __('Low stock'), 'key' => 'low'],
        'in' => ['label' => __('In stock'), 'key' => 'in'],
    ];
@endphp
<x-layouts.user :title="__('Inventory')">
    <div
        class="space-y-6"
        x-data="commerceInventory(@js(['summary' => $summary, 'labels' => ['historyError' => __('Could not load the stock history.')]]))"
        @inventory-updated.window="onUpdated($event)"
    >
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-primary">{{ __('Store commerce') }}</p>
                <h1 class="heading-3 text-title">{{ __('Inventory') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('See what is running out and update stock for every size and color in one place.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.button variant="outline" href="{{ route('user.commerce.products.index') }}"><i class="ph ph-package"></i> {{ __('Products') }}</x-ui.button>
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('Stock summary') }}">
            <a href="{{ route('user.commerce.inventory.index', ['status' => 'out']) }}" @class(['app-card flex items-center gap-4 p-4 transition hover:border-error', 'ring-2 ring-error/40' => $filters['status'] === 'out'])>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-error/10 text-xl text-error"><i class="ph ph-prohibit"></i></span>
                <span>
                    <span class="block text-xs font-medium text-body">{{ __('Out of stock') }}</span>
                    <span class="block text-2xl font-bold text-error" x-text="summary.out.toLocaleString()">{{ number_format($summary['out']) }}</span>
                </span>
            </a>
            <a href="{{ route('user.commerce.inventory.index', ['status' => 'low']) }}" @class(['app-card flex items-center gap-4 p-4 transition hover:border-warning', 'ring-2 ring-warning/40' => $filters['status'] === 'low'])>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-warning/10 text-xl text-warning"><i class="ph ph-warning"></i></span>
                <span>
                    <span class="block text-xs font-medium text-body">{{ __('Low stock') }}</span>
                    <span class="block text-2xl font-bold text-warning" x-text="summary.low.toLocaleString()">{{ number_format($summary['low']) }}</span>
                </span>
            </a>
            <a href="{{ route('user.commerce.inventory.index', ['status' => 'in']) }}" @class(['app-card flex items-center gap-4 p-4 transition hover:border-success', 'ring-2 ring-success/40' => $filters['status'] === 'in'])>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-success/10 text-xl text-success"><i class="ph ph-check-circle"></i></span>
                <span>
                    <span class="block text-xs font-medium text-body">{{ __('In stock') }}</span>
                    <span class="block text-2xl font-bold text-title" x-text="summary.in.toLocaleString()">{{ number_format($summary['in']) }}</span>
                </span>
            </a>
            <div class="app-card flex items-center gap-4 p-4">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-primary/10 text-xl text-primary"><i class="ph ph-stack"></i></span>
                <span>
                    <span class="block text-xs font-medium text-body">{{ __('Total units on hand') }}</span>
                    <span class="block text-2xl font-bold text-title" x-text="summary.units.toLocaleString()">{{ number_format($summary['units']) }}</span>
                </span>
            </div>
        </section>

        <section class="app-card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-border-soft px-4 py-3 lg:flex-row lg:items-center lg:justify-between sm:px-5">
                <nav class="flex flex-wrap items-center gap-1 rounded-md bg-section p-1" aria-label="{{ __('Stock status') }}">
                    @foreach ($tabs as $key => $tab)
                        <a href="{{ route('user.commerce.inventory.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $filters['q'] ?: null, 'category_id' => $filters['category_id']])) }}"
                           @class(['inline-flex items-center gap-1.5 rounded-sm px-3 py-1.5 text-sm font-medium transition', 'bg-neutral-0 text-primary shadow-sm' => $filters['status'] === $key, 'text-body hover:text-primary' => $filters['status'] !== $key])
                           @if ($filters['status'] === $key) aria-current="page" @endif>
                            {{ $tab['label'] }}
                            <span class="rounded-full bg-neutral-100 px-1.5 text-xs text-title" x-text="summary.{{ $tab['key'] }}">{{ $summary[$tab['key']] }}</span>
                        </a>
                    @endforeach
                </nav>
                <form method="GET" action="{{ route('user.commerce.inventory.index') }}" class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
                    @if ($filters['status'] !== 'all')
                        <input type="hidden" name="status" value="{{ $filters['status'] }}">
                    @endif
                    <label class="sr-only" for="inventory-search">{{ __('Search products or SKU') }}</label>
                    <div class="w-full sm:w-56"><input id="inventory-search" type="search" name="q" value="{{ $filters['q'] }}" class="form-input h-9" placeholder="{{ __('Search product or SKU') }}"></div>
                    <label class="sr-only" for="inventory-category">{{ __('Category') }}</label>
                    <div class="w-full sm:w-52"><select id="inventory-category" name="category_id" class="form-input h-9" @change="$el.form.submit()">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters['category_id'] === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select></div>
                    <button type="submit" class="btn btn-sm btn-outline"><i class="ph ph-magnifying-glass"></i> {{ __('Search') }}</button>
                </form>
            </div>

            <p class="flex items-center gap-2 border-b border-border-soft bg-section/60 px-5 py-2 text-xs text-body">
                <i class="ph ph-info text-sm"></i>
                {{ __('Low stock means :count units or fewer.', ['count' => $threshold]) }}
                @can('commerce.orders.settings')
                    <a href="{{ route('user.commerce.orders.settings') }}" class="font-medium text-primary hover:underline">{{ __('Change') }}</a>
                @endcan
                <span class="ml-auto hidden sm:inline">{{ __('Reserved = held for unpaid orders. Available = what shoppers can still buy.') }}</span>
            </p>

            @if ($variants->isEmpty())
                <div class="px-5 py-14 text-center">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-section text-2xl text-neutral-300"><i class="ph ph-package"></i></span>
                    <p class="mt-3 font-semibold text-title">{{ __('No variants match these filters') }}</p>
                    <p class="mt-1 text-sm text-body">{{ __('Try another tab or clear the search.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-border-soft bg-section">
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-body">{{ __('Product') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-body">{{ __('On hand') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-body">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-body">{{ __('Available') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-body">{{ __('Reserved') }}</th>
                                <th class="w-24 px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-body">{{ __('History') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-soft">
                            @foreach ($variants as $variant)
                                @php
                                    $stock = (int) $variant->stock_quantity;
                                    $held = $reserved[$variant->id] ?? 0;
                                    $colorName = $variant->attributes['color'] ?? $variant->color?->name;
                                    $sizeName = $variant->size ?? ($variant->attributes['size'] ?? null);
                                    $stockStatus = $stock <= 0 ? 'out' : ($stock <= $threshold ? 'low' : 'in');
                                    $title = trim($variant->product->name.' '.collect([$colorName, $sizeName])->filter()->implode(' / '));
                                @endphp
                                <tr
                                    class="border-l-4 transition hover:bg-section/70"
                                    :class="{ 'border-l-error bg-error/5': status === 'out', 'border-l-warning bg-warning/5': status === 'low', 'border-l-transparent': status === 'in' }"
                                    x-data="inventoryRow(@js(['url' => route('user.commerce.inventory.adjust', $variant), 'stock' => $stock, 'reserved' => $held, 'available' => $variant->availableQuantity($held), 'status' => $stockStatus, 'labels' => $rowLabels]))"
                                    data-inventory-row="{{ $variant->id }}"
                                >
                                    <td class="px-5 py-3">
                                        <div class="flex min-w-0 items-center gap-3">
                                            @if ($variant->product->primaryMedia)
                                                <img src="{{ $variant->product->primaryMedia->url }}" alt="{{ $variant->product->name }}" class="h-10 w-10 shrink-0 rounded-md border border-neutral-100 object-cover" loading="lazy">
                                            @else
                                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-section text-lg text-neutral-300"><i class="ph ph-t-shirt"></i></span>
                                            @endif
                                            <div class="min-w-0">
                                                @if ($canManage)
                                                    <a href="{{ route('user.commerce.products.edit', ['product' => $variant->product, 'step' => 5]) }}" class="block max-w-72 truncate font-semibold text-title hover:text-primary">{{ $variant->product->name }}</a>
                                                @else
                                                    <span class="block max-w-72 truncate font-semibold text-title">{{ $variant->product->name }}</span>
                                                @endif
                                                <span class="mt-0.5 flex flex-wrap gap-1">
                                                    @if ($colorName)
                                                        <span class="inline-flex rounded-md bg-neutral-100 px-1.5 py-0.5 text-xs text-title">{{ $colorName }}</span>
                                                    @endif
                                                    @if ($sizeName)
                                                        <span class="inline-flex rounded-md bg-neutral-100 px-1.5 py-0.5 text-xs text-title">{{ $sizeName }}</span>
                                                    @endif
                                                    @if ($variant->status !== 'active')
                                                        <span class="inline-flex rounded-md bg-neutral-100 px-1.5 py-0.5 text-xs text-body">{{ str($variant->status)->replace('_', ' ')->title() }}</span>
                                                    @endif
                                                </span>
                                                <span class="mt-0.5 block max-w-72 truncate font-mono text-xs text-body" title="{{ $variant->sku }}">{{ $variant->sku }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($canManage)
                                            <div class="relative flex items-center gap-1.5">
                                                <button type="button" class="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-border text-title transition hover:border-primary hover:text-primary disabled:opacity-40" @click="step(-1)" :disabled="saving || stock <= 0" aria-label="{{ __('Remove one unit of :item', ['item' => $title]) }}"><i class="ph ph-minus"></i></button>
                                                <label class="sr-only" for="stock-{{ $variant->id }}">{{ __('Stock for :item', ['item' => $title]) }}</label>
                                                <div class="w-20 shrink-0"><input id="stock-{{ $variant->id }}" type="number" min="0" max="100000" inputmode="numeric" class="form-input h-8 text-center font-semibold" x-model="draft" :disabled="saving" @keydown.enter.prevent="$el.blur()" @blur="commitDraft()" @keydown.escape="draft = stock; $el.blur()"></div>
                                                <button type="button" class="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-border text-title transition hover:border-primary hover:text-primary disabled:opacity-40" @click="step(1)" :disabled="saving" aria-label="{{ __('Add one unit of :item', ['item' => $title]) }}"><i class="ph ph-plus"></i></button>
                                                <button type="button" class="btn btn-sm btn-outline ml-1" @click="restockOpen = ! restockOpen" :aria-expanded="restockOpen"><i class="ph ph-arrows-down-up"></i> {{ __('Adjust') }}</button>
                                                <i class="ph ph-spinner animate-spin text-primary" x-show="saving" x-cloak></i>

                                                <form class="absolute right-0 top-10 z-20 w-72 space-y-3 rounded-md border border-border bg-neutral-0 p-4 shadow-lg" x-show="restockOpen" x-cloak x-transition @click.outside="restockOpen = false" @keydown.escape.window="restockOpen = false" @submit.prevent="submitRestock()">
                                                    <p class="text-sm font-semibold text-title">{{ __('Adjust :item', ['item' => $title]) }}</p>
                                                    <div>
                                                        <label class="form-label" for="adjust-reason-{{ $variant->id }}">{{ __('Reason') }}</label>
                                                        <select id="adjust-reason-{{ $variant->id }}" class="form-input h-9" x-model="restock.reason">
                                                            <option value="restock">{{ __('Restock (add units)') }}</option>
                                                            <option value="returned">{{ __('Customer return (add units)') }}</option>
                                                            <option value="damaged">{{ __('Damaged or lost (remove units)') }}</option>
                                                            <option value="correction">{{ __('Count correction (+ or −)') }}</option>
                                                            <option value="other">{{ __('Other (+ or −)') }}</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="form-label" for="adjust-qty-{{ $variant->id }}">{{ __('Units') }}</label>
                                                        <input id="adjust-qty-{{ $variant->id }}" type="number" min="-100000" max="100000" class="form-input h-9" x-model="restock.quantity" placeholder="{{ __('e.g. 20') }}" required>
                                                    </div>
                                                    <div>
                                                        <label class="form-label" for="adjust-note-{{ $variant->id }}">{{ __('Note (optional)') }}</label>
                                                        <input id="adjust-note-{{ $variant->id }}" type="text" maxlength="255" class="form-input h-9" x-model="restock.note" placeholder="{{ __('Supplier, invoice no…') }}">
                                                    </div>
                                                    <div class="flex justify-end gap-2">
                                                        <button type="button" class="btn btn-sm btn-outline" @click="restockOpen = false">{{ __('Cancel') }}</button>
                                                        <button type="submit" class="btn btn-sm btn-primary" :disabled="saving">{{ __('Save') }}</button>
                                                    </div>
                                                </form>
                                            </div>
                                        @else
                                            <span class="font-semibold text-title">{{ number_format($stock) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-md px-2 py-0.5 text-xs font-medium"
                                              :class="{ 'bg-error/10 text-error': status === 'out', 'bg-warning/10 text-warning': status === 'low', 'bg-success/10 text-success': status === 'in' }">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span><span x-text="badgeLabel()">{{ $statusLabels[$stockStatus] }}</span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-title" x-text="available">{{ $variant->availableQuantity($held) }}</td>
                                    <td class="px-4 py-3 text-right text-body" x-text="reserved">{{ $held }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <button type="button" class="btn-icon h-8 w-8" @click="openHistory(@js(route('user.commerce.inventory.history', $variant)), @js($title), @js($variant->sku))" aria-label="{{ __('Stock history for :item', ['item' => $title]) }}"><i class="ph ph-clock-counter-clockwise"></i></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-tables.pagination :paginator="$variants" />
            @endif
        </section>

        <x-ui.drawer id="inventory-history-drawer" :title="__('Stock history')">
            <div class="space-y-4">
                <div>
                    <p class="font-semibold text-title" x-text="history.title"></p>
                    <p class="font-mono text-xs text-body" x-text="history.sku"></p>
                </div>
                <p class="text-sm text-body" x-show="history.loading"><i class="ph ph-spinner animate-spin"></i> {{ __('Loading…') }}</p>
                <p class="text-sm text-error" x-show="history.error" x-text="history.error"></p>
                <p class="text-sm text-body" x-show="! history.loading && ! history.error && history.rows.length === 0">{{ __('No stock changes recorded yet.') }}</p>
                <ol class="divide-y divide-border-soft">
                    <template x-for="entry in history.rows" :key="entry.id">
                        <li class="flex items-start justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-title">
                                    <span x-text="entry.reason"></span>
                                    <a x-show="entry.order_url" :href="entry.order_url" class="ml-1 text-xs font-normal text-primary hover:underline">{{ __('View order') }}</a>
                                </p>
                                <p class="text-xs text-body"><span x-text="entry.at"></span><span x-show="entry.by"> · <span x-text="entry.by"></span></span></p>
                                <p class="mt-1 text-xs text-body" x-show="entry.note" x-text="entry.note"></p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-bold" :class="entry.delta > 0 ? 'text-success' : 'text-error'" x-text="(entry.delta > 0 ? '+' : '') + entry.delta"></p>
                                <p class="text-xs text-body" x-show="entry.after !== null" x-text="'→ ' + entry.after"></p>
                            </div>
                        </li>
                    </template>
                </ol>
            </div>
        </x-ui.drawer>
    </div>
</x-layouts.user>
