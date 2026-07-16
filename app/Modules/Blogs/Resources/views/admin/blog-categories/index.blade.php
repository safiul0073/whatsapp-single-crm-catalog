<x-layouts.admin :title="__('Blog Categories')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('Blog Categories') }}</h1>
                <p class="m-text mt-1">{{ __('Organize blog posts into editorial groups.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.blogs.index') }}" class="btn-sm btn-ghost">
                    <i class="ph ph-newspaper"></i>
                    {{ __('Posts') }}
                </a>
                <x-ui.button variant="primary" type="button" data-modal-open="addBlogCategoryModal">
                    <i class="ph ph-plus-circle"></i> {{ __('Add Category') }}
                </x-ui.button>
            </div>
        </div>

        <div class="section-card">
            <x-tables.resource :definition="$table" :items="$blogCategories" />
        </div>
    </div>

    @push('modals')
        <x-ui.modal id="addBlogCategoryModal" :title="__('Add Blog Category')">
            <form method="POST" action="{{ route('admin.blog-categories.store') }}" id="createBlogCategoryForm" class="space-y-4">
                @csrf
                @include('blogs::admin.blog-categories.form', [
                    'blogCategory' => null,
                    'modalId' => 'addBlogCategoryModal',
                ])
            </form>
            <x-slot:footer>
                <div class="flex w-full items-center justify-end gap-3">
                    <x-ui.button type="button" variant="ghost" data-modal-close="addBlogCategoryModal">{{ __('Cancel') }}</x-ui.button>
                    <x-forms.submit :label="__('Create Category')" form="createBlogCategoryForm" />
                </div>
            </x-slot:footer>
        </x-ui.modal>

        @foreach ($blogCategories as $blogCategory)
            <x-ui.modal id="editBlogCategoryModal-{{ $blogCategory->id }}" :title="__('Edit Blog Category')">
                <form method="POST" action="{{ route('admin.blog-categories.update', $blogCategory) }}" id="editBlogCategoryForm-{{ $blogCategory->id }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    @include('blogs::admin.blog-categories.form', [
                        'blogCategory' => $blogCategory,
                        'modalId' => 'editBlogCategoryModal-' . $blogCategory->id,
                    ])
                </form>
                <x-slot:footer>
                    <div class="flex w-full items-center justify-end gap-3">
                        <x-ui.button type="button" variant="ghost" data-modal-close="editBlogCategoryModal-{{ $blogCategory->id }}">{{ __('Cancel') }}</x-ui.button>
                        <x-forms.submit :label="__('Update Category')" form="editBlogCategoryForm-{{ $blogCategory->id }}" />
                    </div>
                </x-slot:footer>
            </x-ui.modal>
        @endforeach
    @endpush

    @if (session('open_modal'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modalId = "{{ session('open_modal') }}";
                const trigger = document.querySelector(`[data-modal-open="${modalId}"]`)
                             || document.querySelector(`[data-modal-trigger="${modalId}"]`);
                if (trigger) {
                    trigger.click();
                } else {
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.classList.remove("hidden");
                        modal.style.display = "flex";
                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                modal.classList.add("active");
                                modal.classList.add("is-open");
                                document.body.classList.add("overflow-hidden");
                                document.body.classList.add("is-locked");
                                modal.querySelector("input, textarea, select, button")?.focus();
                            });
                        });
                    }
                }
            });
        </script>
    @endif
</x-layouts.admin>
