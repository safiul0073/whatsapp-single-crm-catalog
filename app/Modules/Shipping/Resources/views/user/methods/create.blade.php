<x-layouts.user>
    <x-slot:title>
        {{ __('Add Shipping Method') }}
    </x-slot:title>

    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="mb-8">
            <a href="{{ route('user.shipping.methods.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 hover:text-neutral-900 transition-colors">
                <i class="ph ph-arrow-left"></i>
                {{ __('Back to Shipping Methods') }}
            </a>
            <h1 class="text-2xl md:text-3xl text-neutral-800 font-bold mt-2">{{ __('Add Shipping Method') }}</h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Core Details -->
            <div class="lg:col-span-2">
                <div class="bg-white shadow-xs rounded-xl border border-neutral-200 p-6">
                    <h2 class="text-lg font-semibold text-neutral-800 mb-4">{{ __('Method Details') }}</h2>
                    <form action="{{ route('user.shipping.methods.store') }}" method="POST" id="methodForm">
                        @csrf
                        
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="form-label font-semibold text-neutral-700" for="name">{{ __('Method Name') }} <span class="text-error">*</span></label>
                                    <input id="name" type="text" class="form-input w-full mt-1" name="name" value="{{ old('name') }}" placeholder="{{ __('e.g., Express Delivery') }}" required>
                                </div>

                                <div>
                                    <label class="form-label font-semibold text-neutral-700" for="carrier">{{ __('Carrier (Optional)') }}</label>
                                    <input id="carrier" type="text" class="form-input w-full mt-1" name="carrier" value="{{ old('carrier') }}" placeholder="{{ __('e.g., FedEx, UPS, Local Courier') }}">
                                </div>
                            </div>

                            <div>
                                <label class="form-label font-semibold text-neutral-700" for="description">{{ __('Description (Optional)') }}</label>
                                <textarea id="description" name="description" class="form-input text-sm min-h-20 w-full mt-1" rows="3" placeholder="{{ __('Briefly describe this shipping option to customers...') }}">{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column: Settings -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white shadow-xs rounded-xl border border-neutral-200 p-6">
                    <h3 class="text-md font-semibold text-neutral-800 mb-4">{{ __('Delivery Timeframe') }}</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="form-label font-semibold text-neutral-700" for="estimated_delivery_min_days">{{ __('Min Delivery Days') }}</label>
                            <input id="estimated_delivery_min_days" type="number" min="0" class="form-input w-full mt-1" name="estimated_delivery_min_days" value="{{ old('estimated_delivery_min_days') }}" placeholder="1" form="methodForm">
                        </div>
                        
                        <div>
                            <label class="form-label font-semibold text-neutral-700" for="estimated_delivery_max_days">{{ __('Max Delivery Days') }}</label>
                            <input id="estimated_delivery_max_days" type="number" min="0" class="form-input w-full mt-1" name="estimated_delivery_max_days" value="{{ old('estimated_delivery_max_days') }}" placeholder="3" form="methodForm">
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-xs rounded-xl border border-neutral-200 p-6 flex flex-col gap-3">
                    <x-ui.button type="submit" variant="primary" class="w-full justify-center" form="methodForm">
                        <i class="ph ph-check"></i>
                        <span>{{ __('Save Method') }}</span>
                    </x-ui.button>
                    <x-ui.button tag="a" href="{{ route('user.shipping.methods.index') }}" variant="outline" class="w-full justify-center">
                        {{ __('Cancel') }}
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.user>
