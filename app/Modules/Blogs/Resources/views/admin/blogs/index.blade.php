<x-layouts.admin :title="__('Blogs')">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="heading-4 text-neutral-950">{{ __('Blogs') }}</h1>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.blog-categories.index') }}" class="btn-sm btn-ghost">
                    <i class="ph ph-folders"></i>
                    {{ __('Categories') }}
                </a>
                <x-ui.button variant="primary" :href="route('admin.blogs.create')">
                    <i class="ph ph-plus-circle"></i> {{ __('Add Blog Post') }}
                </x-ui.button>
            </div>
        </div>

        <div class="section-card">
            <x-tables.resource :definition="$table" :items="$blogs" />
        </div>
    </div>
</x-layouts.admin>
