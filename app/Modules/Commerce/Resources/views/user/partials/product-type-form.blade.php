@php($uid = $type?->id ?? 'new')
<form
    method="POST"
    action="{{ $action }}"
    class="space-y-4"
    x-data="{
        rows: @js(collect($type?->attributes ?? [])->map(fn ($a) => [
            'label' => $a['label'],
            'type' => $a['type'],
            'options' => implode(', ', $a['options'] ?? []),
            'required' => (bool) ($a['required'] ?? false),
            'filterable' => (bool) ($a['filterable'] ?? false),
            'show_on_card' => (bool) ($a['show_on_card'] ?? false),
        ])->values()),
        add() { this.rows.push({ label: '', type: 'text', options: '', required: false, filterable: false, show_on_card: false }) },
    }"
>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="form-label" for="pt_name_{{ $uid }}">{{ __('Type name') }}</label>
            <input id="pt_name_{{ $uid }}" class="form-input rounded-sm!" name="name" required maxlength="120" value="{{ $type?->name }}" placeholder="{{ __('e.g. Electronics') }}">
        </div>
        <div>
            <label class="form-label" for="pt_layout_{{ $uid }}">{{ __('Storefront layout') }}</label>
            <select id="pt_layout_{{ $uid }}" class="form-input rounded-sm!" name="layout">
                @foreach ($layouts as $layout)
                    <option value="{{ $layout }}" @selected(($type?->layout ?? 'generic') === $layout)>{{ ucfirst($layout) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <fieldset>
        <legend class="form-label">{{ __('Variant options customers choose') }}</legend>
        <div class="flex flex-wrap gap-3">
            @foreach ($axes as $axis)
                <label class="flex items-center gap-2 rounded-md border border-neutral-200 px-3 py-1.5 text-sm">
                    <input type="checkbox" class="app-checkbox" name="variant_axes[]" value="{{ $axis }}" @checked(in_array($axis, $type?->variant_axes ?? [], true))>
                    {{ ucfirst($axis) }}
                </label>
            @endforeach
        </div>
    </fieldset>

    <div>
        <p class="form-label">{{ __('Attributes') }}</p>
        <template x-for="(row, index) in rows" :key="index">
            <div class="mb-2 grid gap-2 rounded-md border border-neutral-200 p-3 md:grid-cols-[1fr_8rem_1fr_auto]">
                <input class="form-input rounded-sm!" :name="`attributes[${index}][label]`" x-model="row.label" placeholder="{{ __('Label, e.g. Metal') }}" maxlength="80" required>
                <select class="form-input rounded-sm!" :name="`attributes[${index}][type]`" x-model="row.type">
                    <option value="text">{{ __('Text') }}</option>
                    <option value="number">{{ __('Number') }}</option>
                    <option value="select">{{ __('Choice') }}</option>
                    <option value="boolean">{{ __('Yes / No') }}</option>
                </select>
                <input class="form-input rounded-sm!" :name="`attributes[${index}][options]`" x-model="row.options" x-show="row.type === 'select'" placeholder="{{ __('Choices, comma separated') }}" maxlength="500">
                <button type="button" class="btn btn-sm btn-outline rounded-sm! text-error" @click="rows.splice(index, 1)" aria-label="{{ __('Remove attribute') }}"><i class="ph ph-x"></i></button>
                <div class="flex flex-wrap gap-4 text-sm md:col-span-4">
                    <label class="flex items-center gap-2"><input type="hidden" :name="`attributes[${index}][required]`" value="0"><input type="checkbox" class="app-checkbox" :name="`attributes[${index}][required]`" value="1" x-model="row.required"> {{ __('Required') }}</label>
                    <label class="flex items-center gap-2"><input type="hidden" :name="`attributes[${index}][filterable]`" value="0"><input type="checkbox" class="app-checkbox" :name="`attributes[${index}][filterable]`" value="1" x-model="row.filterable"> {{ __('Filter on storefront') }}</label>
                    <label class="flex items-center gap-2"><input type="hidden" :name="`attributes[${index}][show_on_card]`" value="0"><input type="checkbox" class="app-checkbox" :name="`attributes[${index}][show_on_card]`" value="1" x-model="row.show_on_card"> {{ __('Show on product card') }}</label>
                </div>
            </div>
        </template>
        <button type="button" class="btn btn-sm btn-outline rounded-sm!" @click="add()"><i class="ph ph-plus"></i> {{ __('Add attribute') }}</button>
    </div>

    <x-forms.submit :label="$type ? __('Save product type') : __('Create product type')" class="rounded-md! shadow-none!" />
</form>
