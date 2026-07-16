<x-layouts.admin :title="__('Create Blog Post')">
    <div class="w-full space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <a href="{{ route('admin.blogs.index') }}" class="row-action" aria-label="{{ __('Back to blogs') }}">
                    <i class="ph ph-arrow-left text-lg"></i>
                </a>
                <div>
                    <h1 class="heading-4 text-neutral-950">{{ __('Add New Post') }}</h1>
                    <p class="m-text mt-1">{{ __('Create a WordPress-style article with publishing controls on the side.') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.blogs.index') }}" class="btn-sm btn-ghost">
                    <i class="ph ph-list-bullets"></i>
                    {{ __('All Posts') }}
                </a>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-error/20 bg-error/10 px-4 py-3 text-sm font-medium text-error">
                {{ __('Please fix the highlighted blog fields.') }}
            </div>
        @endif

        <form id="blogCreateForm" method="POST" action="{{ route('admin.blogs.store') }}" class="blog-post-editor">
            @csrf
            @include('blogs::admin.blogs.form', ['blog' => null])
        </form>
    </div>
</x-layouts.admin>
