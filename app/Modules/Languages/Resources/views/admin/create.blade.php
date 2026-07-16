<x-layouts.admin :title="__('Add Language')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Add Language') }}</h1>
            <x-ui.button variant="outline" href="{{ route('admin.languages.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <div class="section-card">
            <form method="POST" action="{{ route('admin.languages.store') }}" class="space-y-4 max-w-2xl">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-forms.input :label="__('Language Code')" name="code" required placeholder="e.g. en, bn, ar" />
                    <x-forms.input :label="__('Name')" name="name" required placeholder="e.g. English" />
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-forms.input :label="__('Native Name')" name="native_name" required placeholder="e.g. English, বাংলা" />
                    <x-forms.select :label="__('Direction')" name="direction" required>
                        <option value="ltr">{{ __('LTR (Left to Right)') }}</option>
                        <option value="rtl">{{ __('RTL (Right to Left)') }}</option>
                    </x-forms.select>
                </div>
                <x-forms.input :label="__('Sort Order')" name="sort_order" type="number" :value="0" placeholder="0" />
                <div class="flex gap-6">
                    <x-forms.toggle :label="__('Active')" name="is_active" :checked="true" />
                    <x-forms.toggle :label="__('Set as Default')" name="is_default" />
                </div>
                <div class="flex items-center gap-3 pt-4 border-t border-neutral-100">
                    <x-forms.submit :label="__('Create Language')" />
                    <x-ui.button variant="ghost" href="{{ route('admin.languages.index') }}">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
