<x-layouts.user>
    <x-slot:title>
        {{ __('Shipping Methods') }}
    </x-slot:title>

    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl text-neutral-800 font-bold mt-2">{{ __('Shipping Methods') }}</h1>
                <p class="text-sm text-neutral-500 mt-1">{{ __('Manage your delivery carriers, timelines, and shipping options.') }}</p>
            </div>
            <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">
                <x-ui.button tag="a" href="{{ route('user.shipping.methods.create') }}" variant="primary">
                    <i class="ph ph-plus"></i>
                    <span>{{ __('Add Method') }}</span>
                </x-ui.button>
            </div>
        </div>

        <div class="bg-white shadow-xs rounded-xl border border-neutral-200">
            @if($methods->isEmpty())
                <div class="p-8 text-center text-neutral-500">
                    {{ __('No shipping methods found. Add one to get started.') }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-neutral-50 text-neutral-500 border-b border-neutral-200">
                            <tr>
                                <th class="p-4 font-semibold">{{ __('Name') }}</th>
                                <th class="p-4 font-semibold">{{ __('Carrier') }}</th>
                                <th class="p-4 font-semibold">{{ __('Est. Delivery') }}</th>
                                <th class="p-4 font-semibold">{{ __('Status') }}</th>
                                <th class="p-4 font-semibold text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach($methods as $method)
                                <tr class="hover:bg-neutral-50">
                                    <td class="p-4 font-medium text-neutral-800">{{ $method->name }}</td>
                                    <td class="p-4 text-neutral-600">{{ $method->carrier ?? '-' }}</td>
                                    <td class="p-4 text-neutral-600">
                                        @if($method->estimated_delivery_min_days || $method->estimated_delivery_max_days)
                                            {{ $method->estimated_delivery_min_days ?? '0' }} - {{ $method->estimated_delivery_max_days ?? '+' }} {{ __('Days') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="p-4">
                                        @if($method->is_active)
                                            <span class="inline-flex items-center gap-1.5 py-1 px-2 rounded-md text-xs font-medium bg-success/10 text-success">
                                                {{ __('Active') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 py-1 px-2 rounded-md text-xs font-medium bg-neutral-100 text-neutral-600">
                                                {{ __('Inactive') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-right">
                                        <x-ui.button tag="a" href="{{ route('user.shipping.methods.edit', $method) }}" variant="outline" size="sm">
                                            {{ __('Edit') }}
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts.user>
