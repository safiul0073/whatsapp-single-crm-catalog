<div class="modal" id="{{ $id }}" data-modal>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}Title">
        <div class="flex items-center justify-between gap-3">
            <h3 id="{{ $id }}Title" class="heading-4">{{ $title }}</h3>
            <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close') }}">
                <i class="ph ph-x text-base"></i>
            </button>
        </div>
        <form method="POST" action="{{ $action }}" class="mt-4 space-y-4">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="{{ $id }}Name">{{ __('Name') }}</label>
                    <input id="{{ $id }}Name" name="name" type="text" value="{{ old('name', $lead?->name) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label" for="{{ $id }}Company">{{ __('Company') }}</label>
                    <input id="{{ $id }}Company" name="company" type="text" value="{{ old('company', $lead?->company) }}" class="form-input">
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="{{ $id }}Phone">{{ __('WhatsApp number') }}</label>
                    <input id="{{ $id }}Phone" name="phone" type="tel" value="{{ old('phone', $lead?->phone) }}" placeholder="+14155552671" class="form-input">
                </div>
                <div>
                    <label class="form-label" for="{{ $id }}Email">{{ __('Email') }}</label>
                    <input id="{{ $id }}Email" name="email" type="email" value="{{ old('email', $lead?->email) }}" class="form-input">
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="form-label" for="{{ $id }}Country">{{ __('Country') }}</label>
                    <input id="{{ $id }}Country" name="country" type="text" maxlength="2" value="{{ old('country', $lead?->country) }}" class="form-input uppercase">
                </div>
                <div>
                    <label class="form-label" for="{{ $id }}City">{{ __('City') }}</label>
                    <input id="{{ $id }}City" name="city" type="text" value="{{ old('city', $lead?->city) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label" for="{{ $id }}Category">{{ __('Category') }}</label>
                    <input id="{{ $id }}Category" name="category" type="text" value="{{ old('category', $lead?->category) }}" class="form-input">
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="form-label" for="{{ $id }}Stage">{{ __('Stage') }}</label>
                    <select id="{{ $id }}Stage" name="stage" class="form-select ts-basic">
                        @foreach ($stageOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('stage', $lead?->stage ?? 'new') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="{{ $id }}Source">{{ __('Source') }}</label>
                    <select id="{{ $id }}Source" name="source" class="form-select ts-basic">
                        @foreach ($sourceOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('source', $lead?->source ?? 'manual') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="{{ $id }}Score">{{ __('Score') }}</label>
                    <input id="{{ $id }}Score" name="score" type="number" min="0" max="100" value="{{ old('score', $lead?->score) }}" class="form-input">
                </div>
            </div>
            <div>
                <label class="form-label" for="{{ $id }}Notes">{{ __('Notes') }}</label>
                <textarea id="{{ $id }}Notes" name="notes" rows="3" class="form-input">{{ old('notes', $lead?->notes) }}</textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-sm btn-outline" data-modal-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm btn-primary">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
