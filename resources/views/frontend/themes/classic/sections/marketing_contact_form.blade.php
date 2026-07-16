@php $d = $section->data ?? []; @endphp
<section class="spy-section bg-section">
    <div class="container max-w-3xl">
        @if (!empty($d['heading']))
            <div class="text-center">
                <h2 class="heading-1 mt-4">{{ $d['heading'] }}</h2>
            </div>
        @endif

        <div class="mt-10">
            <form method="POST" action="{{ route('contact.submit') }}" class="space-y-5">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">

                @if (session('contact_success'))
                    <div class="mb-5 flex items-center gap-2 rounded-xl bg-primary/10 px-4 py-3 text-sm font-medium text-primary">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ session('contact_success') }}
                    </div>
                @endif

                @php $fields = $d['fields'] ?? []; @endphp
                @if (!empty($fields))
                    @php $chunks = array_chunk($fields, 2); @endphp
                    @foreach ($chunks as $chunk)
                        <div class="grid gap-5 sm:grid-cols-{{ count($chunk) }}">
                            @foreach ($chunk as $field)
                                <div>
                                    <label for="cf_field_{{ $field['name'] ?? '' }}" class="mb-1.5 block text-sm font-semibold text-title">{{ $field['label'] ?? '' }}{{ !empty($field['required']) ? ' *' : '' }}</label>
                                    @if (($field['type'] ?? 'text') === 'textarea')
                                        <textarea id="cf_field_{{ $field['name'] ?? '' }}" name="{{ $field['name'] ?? '' }}" rows="5" {{ !empty($field['required']) ? 'required' : '' }} placeholder="{{ $field['placeholder'] ?? ($field['label'] ?? '') }}" class="form-input resize-y @error($field['name'] ?? '') border-error focus:border-error @enderror">{{ old($field['name'] ?? '') }}</textarea>
                                    @elseif (($field['type'] ?? 'text') === 'select')
                                        @php $interestOptions = $d['interest_options'] ?? []; @endphp
                                        <select id="cf_field_{{ $field['name'] ?? '' }}" name="{{ $field['name'] ?? '' }}" {{ !empty($field['required']) ? 'required' : '' }} class="form-input @error($field['name'] ?? '') border-error focus:border-error @enderror">
                                            <option value="" disabled selected>{{ $field['placeholder'] ?? $field['label'] ?? __('Select...') }}</option>
                                            @foreach ($interestOptions as $option)
                                                <option value="{{ $option['value'] ?? '' }}" {{ old($field['name'] ?? '') === ($option['value'] ?? '') ? 'selected' : '' }}>{{ $option['label'] ?? '' }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input id="cf_field_{{ $field['name'] ?? '' }}" name="{{ $field['name'] ?? '' }}" type="{{ $field['type'] ?? 'text' }}" value="{{ old($field['name'] ?? '') }}" {{ !empty($field['required']) ? 'required' : '' }} placeholder="{{ $field['placeholder'] ?? ($field['label'] ?? '') }}" class="form-input @error($field['name'] ?? '') border-error focus:border-error @enderror" />
                                    @endif
                                    @error($field['name'] ?? '')
                                        <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @endif

                <button type="submit" class="btn btn-primary w-full">
                    {{ $d['submit_text'] ?? __('Send message') }}
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                </button>
            </form>
        </div>
    </div>
</section>
