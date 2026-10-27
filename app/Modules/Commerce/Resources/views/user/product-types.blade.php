<x-layouts.user :title="__('Product types')">
    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-primary">{{ __('Store organization') }}</p>
                <h1 class="heading-3 text-title">{{ __('Product types') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Define the attributes and variant options each kind of product needs, then assign a type to a category.') }}</p>
            </div>
            <x-ui.button variant="outline" href="{{ route('user.commerce.categories.index') }}"><i class="ph ph-tree-structure"></i> {{ __('Categories') }}</x-ui.button>
        </header>

        @if ($errors->any())
            <div class="rounded-md border border-error/30 bg-error/10 p-4 text-sm text-error" role="alert">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="section-card rounded-md!" x-data="{ open: false }">
            <button type="button" class="btn btn-primary rounded-sm!" @click="open = !open"><i class="ph ph-plus"></i> {{ __('New product type') }}</button>
            <div x-show="open" x-cloak class="mt-4">
                @include('commerce::user.partials.product-type-form', ['type' => null, 'action' => route('user.commerce.product-types.store'), 'method' => 'POST'])
            </div>
        </section>

        <div class="space-y-4">
            @foreach ($types as $type)
                <section class="section-card rounded-md!" x-data="{ editing: false }">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="heading-5 text-title">{{ $type->name }}</h2>
                            <p class="text-sm text-body">
                                {{ ucfirst($type->layout) }} · {{ trans_choice(':count attribute|:count attributes', count($type->attributes ?? []), ['count' => count($type->attributes ?? [])]) }}
                                · {{ trans_choice(':count category|:count categories', $type->categories_count, ['count' => $type->categories_count]) }}
                                @if ($type->variant_axes)
                                    · {{ __('Variants') }}: {{ implode(', ', $type->variant_axes) }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline rounded-sm!" @click="editing = !editing">{{ __('Edit') }}</button>
                            <form method="POST" action="{{ route('user.commerce.product-types.destroy', $type) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline rounded-sm! text-error" data-confirm data-confirm-title="{{ __('Delete product type?') }}" data-confirm-body="{{ __('Only types without categories can be deleted.') }}" data-confirm-label="{{ __('Delete') }}" data-confirm-variant="error" @disabled($type->categories_count > 0)>
                                    <i class="ph ph-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div x-show="editing" x-cloak class="mt-4">
                        @include('commerce::user.partials.product-type-form', ['type' => $type, 'action' => route('user.commerce.product-types.update', $type), 'method' => 'PUT'])
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.user>
