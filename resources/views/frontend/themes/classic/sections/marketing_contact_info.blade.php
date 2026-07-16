@php
    $d = $section->data ?? [];
    $channels = $d['channels'] ?? [];

    if (empty($channels)) {
        if (!empty($d['email'])) {
            $channels[] = ['label' => __('Email'), 'value' => $d['email'], 'link_type' => 'email', 'link_url' => $d['email']];
        }
        if (!empty($d['phone'])) {
            $channels[] = ['label' => __('Phone'), 'value' => $d['phone'], 'link_type' => 'phone', 'link_url' => $d['phone']];
        }
        if (!empty($d['address'])) {
            $channels[] = ['label' => __('Address'), 'value' => $d['address'], 'link_type' => 'none'];
        }
    }
@endphp
<section class="spy-section">
    <div class="container grid items-start gap-10 lg:grid-cols-[0.85fr_1.15fr] lg:gap-16">
        <div data-reveal>
            @if (!empty($d['eyebrow']))
                <span class="eyebrow">{{ $d['eyebrow'] }}</span>
            @endif
            @if (!empty($d['heading']))
                <h2 class="heading-2 mt-4">{{ $d['heading'] }}</h2>
            @endif
            @if (!empty($d['subheading']))
                <p class="lead-text mt-4">{{ $d['subheading'] }}</p>
            @endif

            @if (!empty($channels))
                <ul class="mt-8 grid gap-4">
                    @foreach ($channels as $channel)
                        @php
                            $linkType = $channel['link_type'] ?? 'none';
                            $fallbackIcon = match ($linkType) {
                                'phone' => 'ph-phone-call',
                                'url' => 'ph-link-simple',
                                default => str_contains(strtolower($channel['label'] ?? ''), 'address') ? 'ph-map-pin' : 'ph-envelope-simple',
                            };
                            $iconClass = trim($channel['icon_class'] ?? $fallbackIcon);
                            $iconClass = str_contains(' '.$iconClass.' ', ' ph ') ? $iconClass : 'ph '.$iconClass;
                        @endphp
                        <li class="group flex items-center gap-4 rounded-2xl border border-neutral-200 bg-neutral-0 p-4 transition-all duration-300 hover:-translate-y-0.5 hover:border-primary/35">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary transition-colors duration-300 group-hover:bg-primary group-hover:text-neutral-0">
                                <i class="{{ $iconClass }} text-xl"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="font-title text-base font-bold text-title">{{ $channel['label'] ?? '' }}</p>
                                @if ($linkType === 'email')
                                    <a href="mailto:{{ $channel['value'] ?? '' }}" class="m-text mt-1 block break-words text-body transition-colors hover:text-primary">{{ $channel['value'] ?? '' }}</a>
                                @elseif ($linkType === 'phone')
                                    @php
                                        $phoneTarget = (string) ($channel['link_url'] ?? $channel['value'] ?? '');
                                        $phoneTarget = str_starts_with($phoneTarget, 'tel:') ? substr($phoneTarget, 4) : $phoneTarget;
                                        $phoneDigits = preg_replace('/\D+/', '', $phoneTarget) ?: '';
                                        $phoneHref = str_starts_with(trim($phoneTarget), '+') && $phoneDigits !== ''
                                            ? '+'.$phoneDigits
                                            : $phoneDigits;
                                    @endphp
                                    @if ($phoneHref !== '')
                                        <a href="tel:{{ $phoneHref }}" class="m-text mt-1 block break-words text-body transition-colors hover:text-primary">{{ $channel['value'] ?? '' }}</a>
                                    @else
                                        <p class="m-text mt-1 whitespace-pre-line text-body">{{ $channel['value'] ?? '' }}</p>
                                    @endif
                                @elseif ($linkType === 'url' && !empty($channel['link_url']))
                                    <a href="{{ $channel['link_url'] }}" class="m-text text-body transition-colors hover:text-primary">{{ $channel['value'] ?? '' }}</a>
                                @else
                                    <p class="m-text mt-1 whitespace-pre-line text-body">{{ $channel['value'] ?? '' }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if (!empty($d['whatsapp_title']))
                <div class="mt-8 rounded-2xl bg-section p-5">
                    <div class="flex items-center gap-2.5">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-primary text-neutral-0"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z"/></svg></span>
                        <p class="text-sm font-bold text-title">{{ $d['whatsapp_title'] }}</p>
                    </div>
                    @if (!empty($d['whatsapp_hours']))
                        <p class="m-text mt-2">{{ $d['whatsapp_hours'] }}</p>
                    @endif
                </div>
            @endif
        </div>

        <div data-reveal class="rounded-3xl border border-neutral-200 bg-neutral-0 p-6 shadow-[0_30px_70px_-40px_rgba(10,27,20,0.35)] sm:p-8">
            <h2 class="heading-3 mb-5">{{ $d['form_heading'] ?? __('Send us a message') }}</h2>

            <form method="POST" action="{{ route('contact.submit') }}" class="space-y-5">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">

                @php $fields = $d['fields'] ?? []; @endphp
                @if (!empty($fields))
                    @foreach (array_chunk($fields, 2) as $rowFields)
                        <div class="grid gap-5 sm:grid-cols-2">
                            @foreach ($rowFields as $field)
                                <div>
                                    <label for="field_{{ $field['name'] ?? '' }}" class="mb-1.5 block text-sm font-semibold text-title">{{ $field['label'] ?? '' }}{{ !empty($field['required']) ? ' *' : '' }}</label>
                                    @if (($field['type'] ?? 'text') === 'textarea')
                                        <textarea id="field_{{ $field['name'] ?? '' }}" name="{{ $field['name'] ?? '' }}" rows="5" {{ !empty($field['required']) ? 'required' : '' }} placeholder="{{ $field['placeholder'] ?? ($field['label'] ?? '') }}" class="form-input resize-y @error($field['name'] ?? '') border-error focus:border-error @enderror">{{ old($field['name'] ?? '') }}</textarea>
                                    @elseif (($field['type'] ?? 'text') === 'select')
                                        @php $interestOptions = $d['interest_options'] ?? []; @endphp
                                        <select id="field_{{ $field['name'] ?? '' }}" name="{{ $field['name'] ?? '' }}" {{ !empty($field['required']) ? 'required' : '' }} class="form-input @error($field['name'] ?? '') border-error focus:border-error @enderror">
                                            <option value="" disabled selected>{{ $field['placeholder'] ?? $field['label'] ?? __('Select...') }}</option>
                                            @foreach ($interestOptions as $option)
                                                <option value="{{ $option['value'] ?? '' }}" {{ old($field['name'] ?? '') === ($option['value'] ?? '') ? 'selected' : '' }}>{{ $option['label'] ?? '' }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input id="field_{{ $field['name'] ?? '' }}" name="{{ $field['name'] ?? '' }}" type="{{ $field['type'] ?? 'text' }}" value="{{ old($field['name'] ?? '') }}" {{ !empty($field['required']) ? 'required' : '' }} placeholder="{{ $field['placeholder'] ?? ($field['label'] ?? '') }}" class="form-input @error($field['name'] ?? '') border-error focus:border-error @enderror" />
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
