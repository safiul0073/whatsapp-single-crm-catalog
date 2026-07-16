<x-layouts.admin :title="__('FAQs')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('FAQs') }}</h1>
            <x-ui.button variant="primary" type="button" data-modal-open="addFaqModal">
                <i class="ph ph-plus-circle"></i> {{ __('Add FAQ') }}
            </x-ui.button>
        </div>

        <div class="section-card">
            <x-tables.resource :definition="$table" :items="$faqs" />
        </div>
    </div>

    @push('modals')
        {{-- Create FAQ Modal --}}
        <x-ui.modal id="addFaqModal" :title="__('Add FAQ')">
            <form method="POST" action="{{ route('admin.faqs.store') }}" id="createFaqForm" class="space-y-4">
                @csrf
                <x-forms.input
                    :label="__('Question')"
                    name="question"
                    :value="session('open_modal') === 'addFaqModal' ? old('question') : ''"
                    required
                    :placeholder="__('e.g. How do you price a project?')"
                />
                <x-forms.textarea
                    :label="__('Answer')"
                    name="answer"
                    :value="session('open_modal') === 'addFaqModal' ? old('answer') : ''"
                    required
                    :placeholder="__('Write the answer shown on the public FAQ page')"
                />
                <x-forms.select
                    :label="__('Publish Status')"
                    name="status"
                    :selected="session('open_modal') === 'addFaqModal' ? old('status', 'published') : 'published'"
                    :options="['draft' => __('Draft'), 'published' => __('Published'), 'archived' => __('Archived')]"
                />
                <x-forms.input
                    :label="__('Sort Order')"
                    name="sort_order"
                    type="number"
                    :value="session('open_modal') === 'addFaqModal' ? old('sort_order', 0) : 0"
                    required
                    :placeholder="__('e.g. 0')"
                />
                <x-forms.toggle
                    :label="__('Active')"
                    name="active"
                    :checked="session('open_modal') === 'addFaqModal' ? (bool) old('active', true) : true"
                />
            </form>
            <x-slot:footer>
                <div class="flex items-center justify-end gap-3 w-full">
                    <x-ui.button type="button" variant="ghost" data-modal-close="addFaqModal">{{ __('Cancel') }}</x-ui.button>
                    <x-forms.submit :label="__('Create FAQ')" form="createFaqForm" />
                </div>
            </x-slot:footer>
        </x-ui.modal>

        {{-- Edit FAQ Modals --}}
        @foreach ($faqs as $faq)
            <x-ui.modal id="editFaqModal-{{ $faq->id }}" :title="__('Edit FAQ')">
                <form method="POST" action="{{ route('admin.faqs.update', $faq) }}" id="editFaqForm-{{ $faq->id }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="faq_id" value="{{ $faq->id }}">
                    <x-forms.input
                        :label="__('Question')"
                        name="question"
                        :value="session('open_modal') === 'editFaqModal-' . $faq->id ? old('question', $faq->question) : $faq->question"
                        required
                        :placeholder="__('e.g. How do you price a project?')"
                    />
                    <x-forms.textarea
                        :label="__('Answer')"
                        name="answer"
                        :value="session('open_modal') === 'editFaqModal-' . $faq->id ? old('answer', $faq->answer) : $faq->answer"
                        required
                        :placeholder="__('Write the answer shown on the public FAQ page')"
                    />
                    <x-forms.select
                        :label="__('Publish Status')"
                        name="status"
                        :selected="session('open_modal') === 'editFaqModal-' . $faq->id ? old('status', $faq->status) : $faq->status"
                        :options="['draft' => __('Draft'), 'published' => __('Published'), 'archived' => __('Archived')]"
                    />
                    <x-forms.input
                        :label="__('Sort Order')"
                        name="sort_order"
                        type="number"
                        :value="session('open_modal') === 'editFaqModal-' . $faq->id ? old('sort_order', $faq->sort_order) : $faq->sort_order"
                        required
                        :placeholder="__('e.g. 0')"
                    />
                    <x-forms.toggle
                        :label="__('Active')"
                        name="active"
                        :checked="session('open_modal') === 'editFaqModal-' . $faq->id ? (bool) old('active', $faq->active) : (bool) $faq->active"
                    />
                </form>
                <x-slot:footer>
                    <div class="flex items-center justify-end gap-3 w-full">
                        <x-ui.button type="button" variant="ghost" data-modal-close="editFaqModal-{{ $faq->id }}">{{ __('Cancel') }}</x-ui.button>
                        <x-forms.submit :label="__('Update FAQ')" form="editFaqForm-{{ $faq->id }}" />
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
