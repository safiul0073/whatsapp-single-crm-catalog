@if($canUpdate && $order->status === 'awaiting_payment')
    <x-ui.modal id="extendHoldModal" :title="__('Change stock hold time')">
        <form method="POST" action="{{ route('user.commerce.orders.hold', $order) }}" class="space-y-4" x-data="{ mode: '{{ old('until') ? 'until' : 'hours' }}', hours: {{ (int) old('hours', 24) }} }">
            @csrf
            @method('PUT')
            <p class="text-sm text-body">
                {{ $holdExpiresAt ? __('Stock is currently held until :time.', ['time' => $holdExpiresAt->format('M j, Y · g:i A')]) : __('Stock is not held right now. Saving holds it again.') }}
                {{ __('The order is cancelled automatically if it is not paid by the new time.') }}
            </p>
            <div class="flex gap-2" role="radiogroup">
                <button type="button" class="btn btn-sm" :class="mode === 'hours' ? 'btn-primary' : 'btn-outline'" @click="mode = 'hours'">{{ __('Add hours') }}</button>
                <button type="button" class="btn btn-sm" :class="mode === 'until' ? 'btn-primary' : 'btn-outline'" @click="mode = 'until'">{{ __('Pick date & time') }}</button>
            </div>
            <div x-show="mode === 'hours'" class="space-y-3">
                <div class="flex flex-wrap gap-2">
                    @foreach([6, 12, 24, 48, 72] as $preset)
                        <button type="button" class="btn btn-sm" :class="hours === {{ $preset }} ? 'btn-primary' : 'btn-outline'" @click="hours = {{ $preset }}">+{{ $preset }}{{ __('h') }}</button>
                    @endforeach
                </div>
                <div>
                    <label for="hold_hours" class="form-label">{{ __('Hours to add') }}</label>
                    <input id="hold_hours" type="number" name="hours" min="1" max="168" class="form-input" x-model.number="hours" :disabled="mode !== 'hours'" required>
                    <p class="mt-1 text-xs text-body">{{ __('Added to the current deadline, 1–168 hours.') }}</p>
                </div>
            </div>
            <div x-show="mode === 'until'" x-cloak>
                <label for="hold_until" class="form-label">{{ __('Hold until') }}</label>
                <input id="hold_until" type="datetime-local" name="until" class="form-input" value="{{ old('until', $holdExpiresAt?->format('Y-m-d\\TH:i')) }}" min="{{ now()->format('Y-m-d\\TH:i') }}" max="{{ now()->addDays(30)->format('Y-m-d\\TH:i') }}" :disabled="mode !== 'until'" required>
                <p class="mt-1 text-xs text-body">{{ __('Up to 30 days from now. You can also shorten the hold.') }}</p>
            </div>
            @error('hours')<p class="text-sm text-error">{{ $message }}</p>@enderror
            @error('until')<p class="text-sm text-error">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close="extendHoldModal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Save hold time') }}</button>
            </div>
        </form>
    </x-ui.modal>
@endif
