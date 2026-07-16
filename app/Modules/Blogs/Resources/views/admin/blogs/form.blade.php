@php
    $editing = $blog !== null;
    $status = old('status', $blog?->status ?? 'published');
@endphp

@if ($editing)
    <input type="hidden" name="blog_id" value="{{ $blog->id }}">
@endif
<input type="hidden" name="featured_image" value="{{ old('featured_image', $blog?->featured_image) }}">

<div class="blog-post-editor__main space-y-4">
    <section class="space-y-3">
        <div>
            <label for="title" class="sr-only">{{ __('Title') }}</label>
            <input
                id="title"
                name="title"
                type="text"
                value="{{ old('title', $blog?->title) }}"
                required
                class="blog-post-editor__title"
                placeholder="{{ __('Add title') }}"
            >
            @error('title')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="blog-post-editor__permalink">
            <span class="font-semibold text-title">{{ __('Permalink:') }}</span>
            <span>{{ url('/blog') }}/</span>
            <label for="slug" class="sr-only">{{ __('Slug') }}</label>
            <input
                id="slug"
                name="slug"
                type="text"
                value="{{ old('slug', $blog?->slug) }}"
                class="blog-post-editor__slug"
                placeholder="{{ __('auto-generated') }}"
            >
            @error('slug')
                <p class="form-error mt-2">{{ $message }}</p>
            @enderror
        </div>
    </section>

    <section class="blog-post-editor__box">
        <div class="blog-post-editor__box-header">
            <h2 class="blog-post-editor__box-title">{{ __('Editor') }}</h2>
        </div>
        <div class="blog-post-editor__box-body">
            <x-forms.editor
                :label="__('Body')"
                name="content"
                :value="old('content', $blog?->content)"
                :placeholder="__('Start writing or paste your article content...')"
            />
        </div>
    </section>

    <section class="blog-post-editor__box">
        <div class="blog-post-editor__box-header">
            <h2 class="blog-post-editor__box-title">{{ __('Excerpt') }}</h2>
        </div>
        <div class="blog-post-editor__box-body">
            <x-forms.textarea
                :label="__('Excerpt')"
                name="excerpt"
                :value="old('excerpt', $blog?->excerpt)"
                :placeholder="__('Short summary shown on listing cards, social previews, and search results')"
                rows="3"
            />
        </div>
    </section>

    <section class="blog-post-editor__box">
        <div class="blog-post-editor__box-header">
            <h2 class="blog-post-editor__box-title">{{ __('SEO') }}</h2>
        </div>
        <div class="blog-post-editor__box-body grid gap-4 lg:grid-cols-2">
            <x-forms.input
                :label="__('Meta Title')"
                name="meta_title"
                :value="old('meta_title', $blog?->meta_title)"
                :placeholder="__('Optional SEO title')"
            />
            <x-forms.input
                :label="__('Meta Description')"
                name="meta_description"
                :value="old('meta_description', $blog?->meta_description)"
                :placeholder="__('Optional SEO description')"
            />
        </div>
    </section>
</div>

<aside class="blog-post-editor__sidebar space-y-4">
    <section class="blog-post-editor__box">
        <div class="flex items-center justify-between border-b border-border px-4 py-3">
            <h2 class="text-sm font-bold text-title">{{ __('Publish') }}</h2>
            <span class="badge {{ $status === 'published' ? 'badge-success' : 'badge-warning' }}">
                {{ str($status)->headline() }}
            </span>
        </div>
        <div class="space-y-4 p-4">
            <div class="space-y-3 rounded-lg bg-section/60 p-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="inline-flex items-center gap-2 text-body">
                        <i class="ph ph-eye text-base text-neutral-400"></i>
                        {{ __('Visibility') }}
                    </span>
                    <span class="font-semibold text-title">{{ __('Public') }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="inline-flex items-center gap-2 text-body">
                        <i class="ph ph-calendar-check text-base text-neutral-400"></i>
                        {{ __('Publish') }}
                    </span>
                    <span class="font-semibold text-title">{{ __('Immediately') }}</span>
                </div>
            </div>

            <x-forms.select
                :label="__('Status')"
                name="status"
                :selected="$status"
                :options="['draft' => __('Draft'), 'published' => __('Published'), 'archived' => __('Archived')]"
            />
            <x-forms.toggle
                :label="__('Active')"
                name="active"
                :checked="(bool) old('active', $blog?->active ?? true)"
            />
            <div class="grid grid-cols-2 gap-3">
                <x-forms.input
                    :label="__('Read Time')"
                    name="read_time_minutes"
                    type="number"
                    :value="old('read_time_minutes', $blog?->read_time_minutes ?? 5)"
                    required
                />
                <x-forms.input
                    :label="__('Order')"
                    name="sort_order"
                    type="number"
                    :value="old('sort_order', $blog?->sort_order ?? 0)"
                    required
                />
            </div>
            <div class="border-t border-neutral-100 pt-4">
                <button type="submit" class="btn-sm btn-primary w-full">
                    <i class="ph {{ $editing ? 'ph-floppy-disk' : 'ph-paper-plane-tilt' }}"></i>
                    {{ $editing ? __('Update') : __('Publish') }}
                </button>
            </div>
        </div>
    </section>

    <section class="blog-post-editor__box">
        <div class="blog-post-editor__box-header">
            <h2 class="blog-post-editor__box-title">{{ __('Featured Image') }}</h2>
        </div>
        <div class="blog-post-editor__box-body">
            <x-media.picker
                name="featured_image_media_id"
                :value="old('featured_image_media_id', $blog?->featured_image_media_id)"
                accept="image"
                :hint="__('Recommended ratio: 16:10.')"
            />
        </div>
    </section>

    <section class="blog-post-editor__box">
        <div class="blog-post-editor__box-header">
            <h2 class="blog-post-editor__box-title">{{ __('Post Attributes') }}</h2>
        </div>
        <div class="blog-post-editor__box-body space-y-4">
            <x-forms.select
                :label="__('Category')"
                name="blog_category_id"
                :selected="old('blog_category_id', $blog?->blog_category_id)"
                :options="$categoryOptions ?? []"
                :placeholder="__('Uncategorized')"
            />
            <x-forms.input
                :label="__('Author')"
                name="author_name"
                :value="old('author_name', $blog?->author_name)"
                required
                :placeholder="__('e.g. WaPro Editorial')"
            />
        </div>
    </section>

    <section class="rounded-lg border border-dashed border-neutral-200 bg-neutral-0 p-4">
        <div class="flex gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                <i class="ph ph-check-circle text-lg"></i>
            </span>
            <div>
                <h2 class="text-sm font-bold text-title">{{ __('Checklist') }}</h2>
                <ul class="m-text mt-2 space-y-1.5 text-xs">
                    <li>{{ __('Thumbnail selected') }}</li>
                    <li>{{ __('Excerpt is concise') }}</li>
                    <li>{{ __('SEO fields reviewed') }}</li>
                </ul>
            </div>
        </div>
    </section>
</aside>
