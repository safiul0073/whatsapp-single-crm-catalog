<x-layouts.user>
    <x-slot:title>
        {{ __('Edit Shipping Zone: :name', ['name' => $zone->name]) }}
    </x-slot:title>

    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto" x-data="{
        showEditModal: false,
        editForm: { id: '', shipping_method_id: '', min_weight_kg: '', max_weight_kg: '', price: '', updateUrl: '' },
        openEditModal(rate) {
            this.editForm.id = rate.id;
            this.editForm.shipping_method_id = rate.shipping_method_id;
            this.editForm.min_weight_kg = rate.min_weight_kg;
            this.editForm.max_weight_kg = rate.max_weight_kg;
            this.editForm.price = rate.price;
            this.editForm.updateUrl = '{{ url('dashboard/shipping/rates') }}/' + rate.id;
            this.showEditModal = true;
        }
    }">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <a href="{{ route('user.shipping.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 hover:text-neutral-900 transition-colors">
                    <i class="ph ph-arrow-left"></i>
                    {{ __('Back to Shipping Zones') }}
                </a>
                <h1 class="text-2xl md:text-3xl text-neutral-800 font-bold mt-2">{{ $zone->name }}</h1>
            </div>
            
            <form action="{{ route('user.shipping.zones.destroy', $zone) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to delete this zone?') }}');">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger">
                    <i class="ph ph-trash"></i>
                    <span>{{ __('Delete Zone') }}</span>
                </x-ui.button>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Zone Details -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow-xs rounded-xl border border-neutral-200 p-6">
                    <h2 class="text-lg font-semibold text-neutral-800 mb-4">{{ __('Zone Settings') }}</h2>
                    <form action="{{ route('user.shipping.zones.update', $zone) }}" method="POST" class="space-y-6">
                        @csrf
                        @method('PUT')
                        
                        <div>
                            <label class="form-label font-semibold text-neutral-700" for="name">{{ __('Zone Name') }} <span class="text-error">*</span></label>
                            <input id="name" type="text" class="form-input w-full mt-1" name="name" value="{{ old('name', $zone->name) }}" required>
                        </div>

                        <div>
                            <label class="form-label font-semibold text-neutral-700" for="countries">{{ __('Countries') }} <span class="text-error">*</span></label>
                            <select id="countries" name="countries[]" class="ts-multi w-full mt-1" multiple required>
                                @php $selectedCountries = $zone->countries->pluck('country_code')->toArray(); @endphp
                                <option value="US" @selected(in_array('US', $selectedCountries))>United States</option>
                                <option value="CA" @selected(in_array('CA', $selectedCountries))>Canada</option>
                                <option value="GB" @selected(in_array('GB', $selectedCountries))>United Kingdom</option>
                                <option value="AU" @selected(in_array('AU', $selectedCountries))>Australia</option>
                                <option value="IN" @selected(in_array('IN', $selectedCountries))>India</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_active" value="1" class="form-checkbox" @checked(old('is_active', $zone->is_active))>
                                <span class="text-sm font-medium text-neutral-700">{{ __('Zone is active') }}</span>
                            </label>
                        </div>

                        <div class="pt-4 border-t border-neutral-200">
                            <x-ui.button type="submit" variant="primary" class="w-full justify-center">
                                {{ __('Update Zone') }}
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Shipping Rates -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Rates Table -->
                <div class="bg-white shadow-xs rounded-xl border border-neutral-200">
                    <div class="px-6 py-4 border-b border-neutral-200 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-neutral-800">{{ __('Shipping Rates') }}</h2>
                    </div>
                    
                    @if($zone->rates->isEmpty())
                        <div class="p-6 text-center text-neutral-500">
                            {{ __('No rates defined for this zone. Add one below.') }}
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm whitespace-nowrap">
                                <thead class="bg-neutral-50 text-neutral-500 border-b border-neutral-200">
                                    <tr>
                                        <th class="p-4 font-semibold">{{ __('Method') }}</th>
                                        <th class="p-4 font-semibold">{{ __('Weight Range') }}</th>
                                        <th class="p-4 font-semibold">{{ __('Rate') }}</th>
                                        <th class="p-4 font-semibold text-right"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100">
                                    @foreach($zone->rates as $rate)
                                        <tr class="hover:bg-neutral-50">
                                            <td class="p-4 font-medium text-neutral-800">{{ $rate->method->name ?? 'Standard' }}</td>
                                            <td class="p-4 text-neutral-600">
                                                {{ (float) $rate->min_weight_kg }} kg - {{ $rate->max_weight_kg ? (float) $rate->max_weight_kg . ' kg' : 'and up' }}
                                            </td>
                                            <td class="p-4 font-semibold text-neutral-800">${{ number_format($rate->price, 2) }}</td>
                                            <td class="p-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button type="button" @click="openEditModal({ id: {{ $rate->id }}, shipping_method_id: {{ $rate->shipping_method_id }}, min_weight_kg: {{ (float)$rate->min_weight_kg }}, max_weight_kg: {{ $rate->max_weight_kg ? (float)$rate->max_weight_kg : 'null' }}, price: {{ (float)$rate->price }} })" class="text-neutral-500 hover:text-primary p-1">
                                                        <i class="ph ph-pencil-simple"></i>
                                                    </button>
                                                    <form action="{{ route('user.shipping.rates.destroy', $rate) }}" method="POST" onsubmit="return confirm('{{ __('Remove this rate?') }}');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-error hover:text-error/80 p-1">
                                                            <i class="ph ph-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- Add Rate Form -->
                <div class="bg-white shadow-xs rounded-xl border border-neutral-200 p-6">
                    <h3 class="text-md font-semibold text-neutral-800 mb-4">{{ __('Add New Rate') }}</h3>
                    <form action="{{ route('user.shipping.rates.store', $zone) }}" method="POST" class="space-y-4">
                        @csrf
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="form-label font-medium text-neutral-700 text-sm mb-1 block">{{ __('Shipping Method') }}</label>
                                <select name="shipping_method_id" class="ts-basic w-full" required>
                                    @foreach($methods as $method)
                                        <option value="{{ $method->id }}">{{ $method->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div>
                                <label class="form-label font-medium text-neutral-700 text-sm mb-1 block">{{ __('Min Weight (kg)') }}</label>
                                <input type="number" step="0.01" min="0" name="min_weight_kg" class="form-input w-full" placeholder="e.g. 0" required>
                            </div>
                            
                            <div>
                                <label class="form-label font-medium text-neutral-700 text-sm mb-1 block">{{ __('Max Weight (kg, leave blank for no limit)') }}</label>
                                <input type="number" step="0.01" min="0" name="max_weight_kg" class="form-input w-full" placeholder="e.g. 5">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="form-label font-medium text-neutral-700 text-sm mb-1 block">{{ __('Shipping Rate / Cost ($)') }}</label>
                                <input type="number" step="0.01" min="0" name="price" class="form-input w-full" placeholder="e.g. 15.00" required>
                            </div>
                        </div>

                        <div class="pt-4 flex justify-end">
                            <x-ui.button type="submit" variant="primary">
                                <i class="ph ph-plus"></i>
                                <span>{{ __('Add Rate') }}</span>
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Edit Rate Modal -->
        <div x-show="showEditModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showEditModal" x-transition.opacity class="fixed inset-0 bg-neutral-900/50 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="showEditModal = false"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div x-show="showEditModal" x-transition.scale.origin.bottom.sm class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                    <form :action="editForm.updateUrl" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="px-6 py-5 border-b border-neutral-200">
                            <h3 class="text-lg leading-6 font-bold text-neutral-900" id="modal-title">{{ __('Edit Shipping Rate') }}</h3>
                        </div>
                        <div class="px-6 py-5 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="form-label font-medium text-neutral-700 text-sm mb-1 block">{{ __('Shipping Method') }}</label>
                                    <select name="shipping_method_id" class="ts-basic w-full" x-model="editForm.shipping_method_id" required>
                                        @foreach($methods as $method)
                                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="form-label font-medium text-neutral-700 text-sm mb-1 block">{{ __('Min Weight (kg)') }}</label>
                                    <input type="number" step="0.01" min="0" name="min_weight_kg" class="form-input w-full" x-model="editForm.min_weight_kg" required>
                                </div>
                                
                                <div>
                                    <label class="form-label font-medium text-neutral-700 text-sm mb-1 block">{{ __('Max Weight (kg, leave blank for no limit)') }}</label>
                                    <input type="number" step="0.01" min="0" name="max_weight_kg" class="form-input w-full" x-model="editForm.max_weight_kg">
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label class="form-label font-medium text-neutral-700 text-sm mb-1 block">{{ __('Rate / Cost ($)') }}</label>
                                    <input type="number" step="0.01" min="0" name="price" class="form-input w-full" x-model="editForm.price" required>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-neutral-50 flex items-center justify-end gap-3 rounded-b-xl border-t border-neutral-200">
                            <x-ui.button type="button" variant="outline" @click="showEditModal = false">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button type="submit" variant="primary">{{ __('Update Rate') }}</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.user>
