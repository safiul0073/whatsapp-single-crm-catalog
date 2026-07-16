@php
    $modalId = $modalId ?? null;
    $isOpenModal = $modalId && session('open_modal') === $modalId;
@endphp

@if ($blogCategory)
    <input type="hidden" name="blog_category_id" value="{{ $blogCategory->id }}">
@endif

<div class="grid gap-4 sm:grid-cols-2">
    <x-forms.input
        :label="__('Name')"
        name="name"
        :value="$isOpenModal ? old('name', $blogCategory?->name) : $blogCategory?->name"
        required
        :placeholder="__('e.g. Automation')"
    />
    <x-forms.input
        :label="__('Slug')"
        name="slug"
        :value="$isOpenModal ? old('slug', $blogCategory?->slug) : $blogCategory?->slug"
        :placeholder="__('Auto-generated when empty')"
    />
</div>

<x-forms.textarea
    :label="__('Description')"
    name="description"
    :value="$isOpenModal ? old('description', $blogCategory?->description) : $blogCategory?->description"
    :placeholder="__('Short internal description for this category')"
    rows="3"
/>

<div class="grid gap-4 sm:grid-cols-2">
    <x-forms.input
        :label="__('Sort Order')"
        name="sort_order"
        type="number"
        :value="$isOpenModal ? old('sort_order', $blogCategory?->sort_order ?? 0) : ($blogCategory?->sort_order ?? 0)"
        required
    />
    <div class="flex items-end">
        <x-forms.toggle
            :label="__('Active')"
            name="active"
            :checked="$isOpenModal ? (bool) old('active', $blogCategory?->active ?? true) : (bool) ($blogCategory?->active ?? true)"
        />
    </div>
</div>
