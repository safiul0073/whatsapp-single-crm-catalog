<x-layouts.user>
    <x-slot:title>
        {{ __('Shipping Settings') }}
    </x-slot:title>

    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="mb-8">
            <h1 class="text-2xl md:text-3xl text-neutral-800 font-bold">{{ __('Shipping Settings') }}</h1>
            <p class="text-sm text-neutral-500 mt-1">{{ __('Configure global packaging weight and delivery defaults.') }}</p>
        </div>

        <div class="bg-white shadow-xs rounded-xl border border-neutral-200 p-6">
            <form action="{{ route('user.shipping.settings.update') }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')
                
                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_packaging_weight_enabled" value="1" class="app-checkbox" {{ $settings->is_packaging_weight_enabled ? 'checked' : '' }}>
                        <span class="text-sm font-semibold text-neutral-800">{{ __('Enable Packaging Weight') }}</span>
                    </label>
                    <p class="text-xs text-neutral-500 mt-1 ml-6">{{ __('Automatically add the weight of a standard shipping box/mailer to the order total weight.') }}</p>
                </div>

                <div>
                    <label class="form-label font-semibold text-neutral-700" for="default_packaging_weight_kg">{{ __('Default Packaging Weight (kg)') }}</label>
                    <input id="default_packaging_weight_kg" type="number" step="0.001" min="0" class="form-input w-full max-w-xs mt-1 @error('default_packaging_weight_kg') border-error @enderror" name="default_packaging_weight_kg" value="{{ old('default_packaging_weight_kg', $settings->default_packaging_weight_kg) }}">
                    @error('default_packaging_weight_kg')
                        <p class="text-xs text-error mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-4 border-t border-neutral-200">
                    <x-ui.button type="submit" variant="primary">
                        {{ __('Save Settings') }}
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.user>
