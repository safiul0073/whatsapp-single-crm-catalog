<x-layouts.admin :title="__('Add FAQ')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Add FAQ') }}</h1>
            <x-ui.button variant="outline" href="{{ route('admin.faqs.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <div class="grid grid-cols-1 gap-6 2xl:grid-cols-2">
            <div class="section-card">
                <form method="POST" action="{{ route('admin.faqs.store') }}" class="space-y-4 max-w-4xl">
                    @csrf
                    <x-forms.input :label="__('Question')" name="question" required :placeholder="__('e.g. How do you price a project?')" />
                    <x-forms.textarea :label="__('Answer')" name="answer" required :placeholder="__('Write the answer shown on the public FAQ page')" />
                    <x-forms.select
                        :label="__('Publish Status')"
                        name="status"
                        :selected="'published'"
                        :options="['draft' => __('Draft'), 'published' => __('Published'), 'archived' => __('Archived')]"
                    />
                    <x-forms.input :label="__('Sort Order')" name="sort_order" type="number" :value="0" :placeholder="__('e.g. 0')" />
                    <x-forms.toggle :label="__('Active')" name="active" :checked="true" />
                    <div class="flex items-center gap-3 pt-4 border-t border-neutral-100">
                        <x-forms.submit :label="__('Create FAQ')" />
                        <x-ui.button variant="ghost" href="{{ route('admin.faqs.index') }}">{{ __('Cancel') }}</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
