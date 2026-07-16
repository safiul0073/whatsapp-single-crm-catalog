<x-layouts.user :title="__('Open New Ticket')">

    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('user.support-tickets.index') }}"
            class="inline-flex items-center gap-1.5 font-body text-[13px] text-text-muted hover:text-brand-blue transition-colors">
            <i class="ph ph-arrow-left w-4 h-4"></i>
            {{ __('Back to Tickets') }}
        </a>
    </div>

    <div class="max-w-2xl">
        <div class="bg-white rounded-2xl border border-border-soft shadow-xs p-6 space-y-5">

            <div class="flex items-center gap-3 pb-4 border-b border-border-soft">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-tint-blue text-brand-blue">
                    <i class="ph ph-lifebuoy w-5 h-5"></i>
                </div>
                <div>
                    <h2 class="font-display font-bold text-text-strong text-[16px]">{{ __('Open a Support Ticket') }}</h2>
                    <p class="font-body text-[12px] text-text-muted">{{ __('Describe your issue and we\'ll get back to you.') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('user.support-tickets.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-[12px] font-semibold text-text-strong mb-1.5">
                        {{ __('Subject') }} <span class="text-error">*</span>
                    </label>
                    <input type="text" name="subject" value="{{ old('subject') }}" required
                        placeholder="{{ __('Brief summary of your issue') }}"
                        class="portal-input w-full" />
                    @error('subject')
                        <p class="mt-1 text-[11px] text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-[12px] font-semibold text-text-strong mb-1.5">
                        {{ __('Priority') }} <span class="text-error">*</span>
                    </label>
                    <select name="priority" required class="portal-input w-full">
                        @foreach (['low' => __('Low'), 'medium' => __('Medium'), 'high' => __('High'), 'urgent' => __('Urgent')] as $value => $label)
                            <option value="{{ $value }}" {{ old('priority', 'medium') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('priority')
                        <p class="mt-1 text-[11px] text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-[12px] font-semibold text-text-strong mb-1.5">
                        {{ __('Message') }} <span class="text-error">*</span>
                    </label>
                    <textarea name="message" rows="7" required
                        placeholder="{{ __('Describe your issue in detail...') }}"
                        class="portal-input w-full resize-none">{{ old('message') }}</textarea>
                    @error('message')
                        <p class="mt-1 text-[11px] text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-[12px] font-semibold text-text-strong mb-1.5">
                        {{ __('Attachments') }}
                        <span class="text-text-muted font-normal">({{ __('optional') }})</span>
                    </label>
                    <input type="file" name="attachments[]" multiple
                        accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip"
                        class="block w-full text-[12px] text-text-muted file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-bg-soft file:text-text-strong file:font-medium hover:file:bg-border-soft transition-colors">
                    <p class="text-[11px] text-text-muted mt-1.5">
                        {{ __('Max :size MB each. Images, PDF, DOC, XLS, CSV, TXT, ZIP.', ['size' => config('support-tickets.attachments.max_size') / 1024]) }}
                    </p>
                    @error('attachments.*')
                        <p class="mt-1 text-[11px] text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-1 flex items-center gap-3">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-blue px-6 py-2.5 font-body font-semibold text-[13px] text-white shadow-brand hover:bg-primary-hover transition-colors">
                        <i class="ph ph-send w-4 h-4"></i>
                        {{ __('Submit Ticket') }}
                    </button>
                    <a href="{{ route('user.support-tickets.index') }}"
                        class="font-body text-[13px] text-text-muted hover:text-text-strong transition-colors">
                        {{ __('Cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

</x-layouts.user>
