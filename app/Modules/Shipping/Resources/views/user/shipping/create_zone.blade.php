<x-layouts.user>
    <x-slot:title>
        {{ __('Create Shipping Zone') }}
    </x-slot:title>

    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="mb-8">
            <a href="{{ route('user.shipping.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 hover:text-neutral-900 transition-colors">
                <i class="ph ph-arrow-left"></i>
                {{ __('Back to Shipping Zones') }}
            </a>
            <h1 class="text-2xl md:text-3xl text-neutral-800 font-bold mt-2">{{ __('Create Shipping Zone') }}</h1>
            <p class="text-sm text-neutral-500 mt-1">{{ __('Define a geographical region and the countries it covers.') }}</p>
        </div>

        <div class="bg-white shadow-xs rounded-xl border border-neutral-200 p-6">
            <form action="{{ route('user.shipping.zones.store') }}" method="POST" class="space-y-6">
                @csrf
                
                <div>
                    <label class="form-label font-semibold text-neutral-700" for="name">{{ __('Zone Name') }} <span class="text-error">*</span></label>
                    <input id="name" type="text" class="form-input w-full max-w-md mt-1" name="name" value="{{ old('name') }}" placeholder="{{ __('e.g., North America, Europe') }}" required>
                </div>

                <div>
                    <label class="form-label font-semibold text-neutral-700" for="countries">{{ __('Countries') }} <span class="text-error">*</span></label>
                    <select id="countries" name="countries[]" class="ts-multi w-full max-w-md mt-1" multiple required>
                        <option value="US">United States</option>
                        <option value="CA">Canada</option>
                        <option value="GB">United Kingdom</option>
                        <option value="AU">Australia</option>
                        <option value="IN">India</option>
                        <!-- In a real app, populate with all ISO country codes -->
                    </select>
                    <p class="text-xs text-neutral-500 mt-1">{{ __('Hold Ctrl/Cmd to select multiple countries.') }}</p>
                </div>

                <div class="pt-4 border-t border-neutral-200 flex items-center gap-3">
                    <x-ui.button type="submit" variant="primary">
                        {{ __('Save Zone') }}
                    </x-ui.button>
                    <x-ui.button tag="a" href="{{ route('user.shipping.index') }}" variant="outline">
                        {{ __('Cancel') }}
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.user>
