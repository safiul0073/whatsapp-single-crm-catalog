        @if($canViewCustomer)
        @can('commerce.orders.customer.edit')
        <x-ui.modal id="editCustomerModal" :title="__('Edit Customer Contact & Address')">
            <form method="POST" action="{{ route('user.commerce.orders.customer.update', $order) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="edit_customer_name" class="form-label">{{ __('Name') }} <span class="text-error">*</span></label>
                        <input id="edit_customer_name" type="text" name="name" class="form-input" value="{{ old('name', $customerName) }}" required>
                    </div>
                    <div>
                        <label for="edit_customer_phone" class="form-label">{{ __('Phone') }}</label>
                        <input id="edit_customer_phone" type="text" name="phone" class="form-input" value="{{ old('phone', $customerPhone) }}">
                    </div>
                    <div class="md:col-span-2">
                        <label for="edit_customer_email" class="form-label">{{ __('Email') }}</label>
                        <input id="edit_customer_email" type="email" name="email" class="form-input" value="{{ old('email', $customerEmail) }}">
                    </div>
                </div>
                <div class="border-t border-border pt-3">
                    <h4 class="font-medium text-title text-sm mb-3">{{ __('Contact Address') }}</h4>
                    <div class="space-y-3">
                        <div>
                            <label for="edit_customer_line1" class="form-label">{{ __('Address Line 1') }}</label>
                            <input id="edit_customer_line1" type="text" name="address_line1" class="form-input" value="{{ old('address_line1', $contactAddr['line1'] ?? '') }}" placeholder="{{ __('Street address, P.O. Box') }}">
                        </div>
                        <div>
                            <label for="edit_customer_line2" class="form-label">{{ __('Address Line 2') }}</label>
                            <input id="edit_customer_line2" type="text" name="address_line2" class="form-input" value="{{ old('address_line2', $contactAddr['line2'] ?? '') }}" placeholder="{{ __('Apartment, suite, unit, building, floor') }}">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="edit_customer_city" class="form-label">{{ __('City') }}</label>
                                <input id="edit_customer_city" type="text" name="city" class="form-input" value="{{ old('city', $contactAddr['city'] ?? '') }}">
                            </div>
                            <div>
                                <label for="edit_customer_state" class="form-label">{{ __('State / Province') }}</label>
                                <input id="edit_customer_state" type="text" name="state" class="form-input" value="{{ old('state', $contactAddr['state'] ?? '') }}">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="edit_customer_postal_code" class="form-label">{{ __('Postal / ZIP Code') }}</label>
                                <input id="edit_customer_postal_code" type="text" name="postal_code" class="form-input" value="{{ old('postal_code', $contactAddr['postal_code'] ?? '') }}">
                            </div>
                            <div>
                                <label for="edit_customer_country" class="form-label">{{ __('Country') }}</label>
                                <input id="edit_customer_country" type="text" name="country" class="form-input" value="{{ old('country', $contactAddr['country'] ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn btn-outline btn-sm" data-modal-close="editCustomerModal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Save changes') }}</button>
                </div>
            </form>
        </x-ui.modal>
        @endcan
        @endif
