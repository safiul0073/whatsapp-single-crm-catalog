<x-layouts.user>
    <x-slot:title>
        {{ __('Shipping Zones & Rates') }}
    </x-slot:title>

    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="sm:flex sm:justify-between sm:items-center mb-8">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl text-neutral-800 font-bold">{{ __('Shipping Zones') }}</h1>
                <p class="text-sm text-neutral-500 mt-1">{{ __('Manage regions and the rates customers pay for shipping.') }}</p>
            </div>
            <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">
                <x-ui.button tag="a" href="{{ route('user.shipping.zones.create') }}" variant="primary">
                    <i class="ph ph-plus"></i>
                    <span>{{ __('Create Zone') }}</span>
                </x-ui.button>
            </div>
        </div>

        <div class="bg-white shadow-xs rounded-xl border border-neutral-200">
            @forelse($zones as $zone)
                <div class="p-5 border-b border-neutral-200 last:border-b-0">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-neutral-800">{{ $zone->name }}</h2>
                            <p class="text-sm text-neutral-500 mt-0.5">
                                {{ $zone->countries->pluck('country_code')->join(', ') ?: __('No countries assigned') }}
                            </p>
                        </div>
                        <x-ui.button tag="a" href="{{ route('user.shipping.zones.edit', $zone) }}" variant="outline" size="sm">
                            {{ __('Edit Zone') }}
                        </x-ui.button>
                    </div>

                    <div class="mt-4">
                        <table class="w-full text-left text-sm text-neutral-600">
                            <thead class="bg-neutral-50 border-y border-neutral-200">
                                <tr>
                                    <th class="py-2 px-4 font-semibold">{{ __('Method') }}</th>
                                    <th class="py-2 px-4 font-semibold">{{ __('Weight Range') }}</th>
                                    <th class="py-2 px-4 font-semibold">{{ __('Price') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($zone->rates as $rate)
                                    <tr class="border-b border-neutral-100 last:border-b-0">
                                        <td class="py-2 px-4">{{ $rate->method->name ?? 'Standard' }}</td>
                                        <td class="py-2 px-4">
                                            {{ (float) $rate->min_weight_kg }} kg - {{ $rate->max_weight_kg ? (float) $rate->max_weight_kg . ' kg' : 'and up' }}
                                        </td>
                                        <td class="py-2 px-4 font-semibold">${{ number_format($rate->price, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-4 px-4 text-center text-neutral-500">{{ __('No rates configured for this zone.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-neutral-100 mb-4">
                        <i class="ph ph-globe text-2xl text-neutral-500"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-neutral-800">{{ __('No Shipping Zones') }}</h2>
                    <p class="text-sm text-neutral-500 mt-1 max-w-sm mx-auto">{{ __('Create a shipping zone to configure where you ship to and how much to charge.') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.user>
