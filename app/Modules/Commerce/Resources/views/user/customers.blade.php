<x-layouts.user :title="__('Commerce customers')">
    @php
        $formErrors = $errors->getBag('customerForm');
        $countryOptions = app(\App\Modules\Contacts\Services\ContactService::class)->countryOptions();
        $oldForm = $formErrors->any() ? [
            'update_url' => old('form_update_url', ''),
            'name' => old('name', ''),
            'phone' => old('phone', ''),
            'email' => old('email', ''),
            'address' => [
                'line1' => old('address.line1', ''),
                'line2' => old('address.line2', ''),
                'city' => old('address.city', ''),
                'state' => old('address.state', ''),
                'postal_code' => old('address.postal_code', ''),
                'country' => old('address.country', ''),
            ],
        ] : null;
    @endphp
    <div class="space-y-6" x-data="{
        search: {{ Js::from((string) request('search', '')) }},
        loadingList: false,
        listController: null,
        total: {{ Js::from($customers->total()) }},
        firstItem: {{ Js::from($customers->firstItem()) }},
        lastItem: {{ Js::from($customers->lastItem()) }},
        formOpen: false,
        form: null,
        blankForm() {
            return { update_url: '', name: '', phone: '', email: '', address: { line1: '', line2: '', city: '', state: '', postal_code: '', country: '' } };
        },
        init() {
            this.form = this.blankForm();
            this.$watch('search', () => this.fetchList(this.searchUrl()));
            const old = {{ Js::from($oldForm) }};
            if (old) {
                this.form = old;
                this.formOpen = true;
            }
        },
        searchUrl() {
            const url = new URL('{{ route('user.commerce.customers.index') }}', window.location.origin);
            const term = this.search.trim();
            if (term !== '') {
                url.searchParams.set('search', term);
            }
            return url.toString();
        },
        clearSearch() {
            this.search = '';
        },
        fetchList(url) {
            if (this.listController) {
                this.listController.abort();
            }
            this.listController = new AbortController();
            this.loadingList = true;
            fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: this.listController.signal
            })
            .then(res => res.json())
            .then(data => {
                this.$refs.rows.innerHTML = data.html;
                this.$refs.pagination.innerHTML = data.pagination;
                this.total = data.total;
                this.firstItem = data.first;
                this.lastItem = data.last;
                window.history.replaceState(null, '', url);
                this.loadingList = false;
            })
            .catch(error => {
                if (error.name !== 'AbortError') {
                    this.loadingList = false;
                }
            });
        },
        paginate(event) {
            const link = event.target.closest('a[href]');
            if (link) {
                event.preventDefault();
                this.fetchList(link.href);
            }
        },
        openForm(customer = null) {
            this.form = customer ? JSON.parse(JSON.stringify(customer)) : this.blankForm();
            this.activeCustomer = null;
            this.formOpen = true;
        },
        closeForm() {
            this.formOpen = false;
        },
        activeCustomer: null,
        loadingDetails: false,
        openDetails(customer) {
            this.activeCustomer = customer;
            this.loadingDetails = true;
            fetch('{{ route('user.commerce.customers.index') }}/' + customer.id, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                const edit = this.activeCustomer ? this.activeCustomer.edit : null;
                const summary = this.activeCustomer || {};
                this.activeCustomer = { ...summary, ...data.customer, name: summary.name, phone: summary.phone, email: summary.email, registered_at: summary.registered_at, verified_at: summary.verified_at, addresses: summary.addresses, edit: edit };
                this.activeCustomer.orders = data.orders || [];
                this.loadingDetails = false;
            })
            .catch(() => {
                this.loadingDetails = false;
            });
        },
        closeDetails() {
            this.activeCustomer = null;
        }
    }">
        {{-- Header & Action Buttons --}}
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-wider uppercase text-primary">{{ __('Commerce Management') }}</p>
                <h1 class="heading-3 text-title font-bold">{{ __('Customers') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Customers registered and authenticated through Storefront and Mobile App.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                @can('commerce.customers.manage')
                    <button type="button" @click="openForm()" class="btn btn-sm btn-primary inline-flex items-center gap-1.5 shadow-xs">
                        <i class="ph ph-user-plus text-base"></i>
                        <span>{{ __('Add customer') }}</span>
                    </button>
                @endcan
                <x-ui.button variant="outline" size="sm" href="{{ route('user.commerce.orders.index') }}" class="shadow-xs hover:border-primary hover:text-primary">
                    <i class="ph ph-package text-base"></i>
                    <span>{{ __('Orders') }}</span>
                </x-ui.button>
                <x-ui.button variant="outline" size="sm" href="{{ route('user.commerce.products.index') }}" class="shadow-xs hover:border-primary hover:text-primary">
                    <i class="ph ph-t-shirt text-base"></i>
                    <span>{{ __('Products') }}</span>
                </x-ui.button>
                @can('commerce.pos.use')
                    <x-ui.button variant="outline" size="sm" href="{{ route('user.commerce.pos.index') }}" class="shadow-xs hover:border-primary hover:text-primary">
                        <i class="ph ph-cash-register text-base"></i>
                        <span>{{ __('POS') }}</span>
                    </x-ui.button>
                @endcan
                @can('commerce.storefront.settings')
                    <x-ui.button variant="outline" size="sm" href="{{ route('user.commerce.storefront.index') }}" class="shadow-xs hover:border-primary hover:text-primary">
                        <i class="ph ph-browser text-base"></i>
                        <span>{{ __('Storefront Studio') }}</span>
                    </x-ui.button>
                @endcan
            </div>
        </header>

        {{-- Filters & Search Bar --}}
        <div class="card p-4 sm:p-5">
            <form method="GET" action="{{ route('user.commerce.customers.index') }}" @submit.prevent="fetchList(searchUrl())" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                {{-- Instant Search Box --}}
                <div class="relative flex-1">
                    <i class="ph ph-magnifying-glass pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-neutral-400" x-show="!loadingList"></i>
                    <i class="ph ph-spinner animate-spin pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-primary" x-show="loadingList" x-cloak></i>
                    <input
                        type="search"
                        name="search"
                        x-model.debounce.350ms="search"
                        value="{{ request('search') }}"
                        autocomplete="off"
                        aria-label="{{ __('Search customers') }}"
                        placeholder="{{ __('Search customer name, phone, email, or city...') }}"
                        class="form-input input-search !pl-11 !pr-10 w-full py-2 text-sm rounded-lg border-neutral-200 bg-neutral-0 placeholder:text-neutral-400 focus:border-primary focus:ring-2 focus:ring-primary/20 [&::-webkit-search-cancel-button]:hidden [&::-webkit-search-decoration]:hidden"
                    />
                    <button type="button" x-show="search !== ''" x-cloak @click="clearSearch()" class="absolute top-1/2 right-3 -translate-y-1/2 flex items-center text-neutral-400 hover:text-neutral-600" title="{{ __('Clear search') }}">
                        <i class="ph ph-x text-sm"></i>
                    </button>
                </div>
                <p class="text-xs text-neutral-500 sm:w-48 sm:text-right">
                    <span x-show="search === ''">{{ __('Results update as you type.') }}</span>
                    <span x-show="search !== ''" x-cloak>{{ __('Matches for') }} "<span class="font-semibold text-title" x-text="search"></span>"</span>
                </p>
            </form>
        </div>

        {{-- Customers Table Section --}}
        <section class="card overflow-hidden p-0">
            {{-- Table Meta Header --}}
            <div class="flex flex-col gap-2 border-b border-border-soft px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between bg-neutral-50/50">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-title">{{ __('Customers list') }}</h2>
                    <span class="rounded-full bg-neutral-200/80 px-2 py-0.5 text-[11px] font-bold text-neutral-600" x-text="total">{{ $customers->total() }}</span>
                </div>
                <div class="text-xs text-body">
                    <span x-show="total > 0">{{ __('Showing') }} <span class="font-semibold text-title" x-text="firstItem"></span>–<span class="font-semibold text-title" x-text="lastItem"></span> {{ __('of') }} <span class="font-semibold text-title" x-text="total"></span></span>
                    <span x-show="total === 0" x-cloak>{{ __('0 customers found') }}</span>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-left text-sm border-collapse">
                    <thead>
                        <tr class="border-b border-border-soft bg-neutral-50/75 text-xs font-semibold uppercase tracking-wider text-neutral-600">
                            <th class="py-3 px-4">{{ __('Customer') }}</th>
                            <th class="py-3 px-4">{{ __('Channel') }}</th>
                            <th class="py-3 px-4">{{ __('Status') }}</th>
                            <th class="py-3 px-4">{{ __('Orders & Lifetime spend') }}</th>
                            <th class="py-3 px-4">{{ __('Default shipping address') }}</th>
                            <th class="py-3 px-4">{{ __('Registered') }}</th>
                            <th class="py-3 px-4 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft transition-opacity" x-ref="rows" :class="loadingList && 'opacity-50'">
                        @include('commerce::user.partials.customers.rows', ['customers' => $customers, 'spending' => $spending])
                    </tbody>
                </table>
            </div>

            {{-- Pagination Footer --}}
            <div x-ref="pagination" @click="paginate($event)" class="border-t border-border-soft px-5 py-4 empty:hidden">@if($customers->hasPages()){{ $customers->links() }}@endif</div>
        </section>

        {{-- Slide-over Drawer / Modal for Customer Details --}}
        <div
            x-show="activeCustomer !== null"
            x-cloak
            class="fixed inset-0 z-50 overflow-hidden"
            aria-labelledby="slide-over-title"
            role="dialog"
            aria-modal="true"
        >
            {{-- Backdrop --}}
            <div
                x-show="activeCustomer !== null"
                x-transition:enter="ease-in-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in-out duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeDetails()"
                class="fixed inset-0 bg-neutral-900/40 backdrop-blur-xs transition-opacity"
            ></div>

            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div
                    x-show="activeCustomer !== null"
                    x-transition:enter="transform transition ease-in-out duration-300"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition ease-in-out duration-200"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="pointer-events-auto w-screen max-w-md bg-neutral-0 shadow-2xl flex flex-col"
                >
                    {{-- Drawer Header --}}
                    <div class="flex items-center justify-between border-b border-border-soft px-5 py-4 bg-neutral-50/60">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary border border-primary/20 uppercase shrink-0" x-text="activeCustomer ? activeCustomer.name.charAt(0) : ''"></span>
                            <div>
                                <h3 class="text-base font-semibold text-title" x-text="activeCustomer ? activeCustomer.name : ''"></h3>
                                <p class="text-xs text-neutral-500 font-mono" x-text="activeCustomer ? activeCustomer.phone : ''"></p>
                            </div>
                        </div>
                        <button type="button" @click="closeDetails()" class="rounded-md p-1.5 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 transition">
                            <i class="ph ph-x text-lg"></i>
                        </button>
                    </div>

                    {{-- Drawer Body --}}
                    <div class="flex-1 overflow-y-auto p-5 space-y-6">
                        {{-- Account Summary Card --}}
                        <div class="rounded-lg border border-border-soft p-4 bg-neutral-50/40 space-y-3">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ __('Account Details') }}</h4>
                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <span class="text-neutral-400 block">{{ __('Registration Channel') }}</span>
                                    <span class="font-medium text-title inline-flex items-center gap-1 mt-0.5" x-text="activeCustomer ? activeCustomer.channel_label : ''"></span>
                                </div>
                                <div>
                                    <span class="text-neutral-400 block">{{ __('Phone / WhatsApp') }}</span>
                                    <span class="font-medium text-title inline-flex items-center gap-1 mt-0.5">
                                        <template x-if="activeCustomer && activeCustomer.verified_at">
                                            <span class="text-emerald-600 font-semibold flex items-center gap-1"><i class="ph ph-check-circle"></i> Verified</span>
                                        </template>
                                        <template x-if="activeCustomer && !activeCustomer.verified_at">
                                            <span class="text-neutral-500">Unverified</span>
                                        </template>
                                    </span>
                                </div>
                                <div class="col-span-2" x-show="activeCustomer && activeCustomer.email">
                                    <span class="text-neutral-400 block">{{ __('Email Address') }}</span>
                                    <span class="font-medium text-title" x-text="activeCustomer ? activeCustomer.email : ''"></span>
                                </div>
                                <div>
                                    <span class="text-neutral-400 block">{{ __('Joined Date') }}</span>
                                    <span class="font-medium text-title" x-text="activeCustomer ? activeCustomer.registered_at : ''"></span>
                                </div>
                                <div>
                                    <span class="text-neutral-400 block">{{ __('Orders Placed') }}</span>
                                    <span class="font-medium text-title" x-text="activeCustomer ? activeCustomer.orders_count + ' orders' : '0 orders'"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Saved Addresses --}}
                        <div class="space-y-3">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ __('Shipping Addresses') }}</h4>
                            <template x-if="!activeCustomer || !activeCustomer.addresses || activeCustomer.addresses.length === 0">
                                <p class="text-xs text-neutral-400 italic">{{ __('No addresses saved yet.') }}</p>
                            </template>
                            <template x-for="(addr, idx) in (activeCustomer ? activeCustomer.addresses : [])" :key="idx">
                                <div class="rounded-lg border border-border-soft p-3 text-xs space-y-1 bg-neutral-0">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-title" x-text="addr.label || 'Address'"></span>
                                        <template x-if="addr.is_default">
                                            <span class="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary">{{ __('Default') }}</span>
                                        </template>
                                    </div>
                                    <p class="text-neutral-600" x-text="[addr.address.line1, addr.address.line2].filter(Boolean).join(', ')"></p>
                                    <p class="text-neutral-500" x-text="[addr.address.city, addr.address.state, addr.address.postal_code, addr.address.country].filter(Boolean).join(', ')"></p>
                                </div>
                            </template>
                        </div>

                        {{-- Recent Orders --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ __('Recent Orders') }}</h4>
                                <template x-if="activeCustomer && activeCustomer.contact_id">
                                    <a :href="'{{ route('user.commerce.orders.index') }}?contact_id=' + activeCustomer.contact_id" class="text-xs font-semibold text-primary hover:underline">
                                        {{ __('View all in orders') }} →
                                    </a>
                                </template>
                            </div>

                            <template x-if="loadingDetails">
                                <div class="py-6 text-center text-xs text-neutral-400">
                                    <i class="ph ph-spinner animate-spin text-lg mb-1"></i>
                                    <p>{{ __('Loading orders...') }}</p>
                                </div>
                            </template>

                            <template x-if="!loadingDetails && (!activeCustomer || !activeCustomer.orders || activeCustomer.orders.length === 0)">
                                <p class="text-xs text-neutral-400 italic">{{ __('No orders placed yet.') }}</p>
                            </template>

                            <template x-if="!loadingDetails && activeCustomer && activeCustomer.orders && activeCustomer.orders.length > 0">
                                <div class="space-y-2">
                                    <template x-for="ord in activeCustomer.orders" :key="ord.id">
                                        <a :href="'{{ url('dashboard/commerce/orders') }}/' + ord.id" class="block rounded-lg border border-border-soft p-3 hover:border-primary transition bg-neutral-0">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="font-mono font-semibold text-primary" x-text="ord.number"></span>
                                                <span class="font-bold text-title" x-text="((ord.currency === 'USD' || !ord.currency) ? '$' : ord.currency + ' ') + Number(ord.total || ord.subtotal || 0).toFixed(2)"></span>
                                            </div>
                                            <div class="mt-1 flex items-center justify-between text-[11px] text-neutral-400">
                                                <span class="capitalize" x-text="(ord.status || '').replace('_', ' ')"></span>
                                                <span x-text="ord.created_at ? new Date(ord.created_at).toLocaleDateString() : ''"></span>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Drawer Footer --}}
                    <div class="border-t border-border-soft p-4 bg-neutral-50/50 flex items-center justify-end gap-2">
                        @can('commerce.customers.manage')
                            <template x-if="activeCustomer && activeCustomer.edit">
                                <button type="button" @click="openForm(activeCustomer.edit)" class="btn btn-sm btn-outline inline-flex items-center gap-1.5">
                                    <i class="ph ph-pencil-simple"></i>
                                    <span>{{ __('Edit') }}</span>
                                </button>
                            </template>
                        @endcan
                        <template x-if="activeCustomer && activeCustomer.contact_id">
                            <a :href="'{{ route('user.commerce.orders.index') }}?contact_id=' + activeCustomer.contact_id" class="btn btn-sm btn-primary inline-flex items-center gap-1.5 shadow-xs">
                                <i class="ph ph-shopping-bag"></i>
                                <span>{{ __('View customer orders') }}</span>
                            </a>
                        </template>
                        <button type="button" @click="closeDetails()" class="btn btn-sm btn-outline">
                            {{ __('Close') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @can('commerce.customers.manage')
            {{-- Add / Edit Customer Modal --}}
            <div x-show="formOpen" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-8" role="dialog" aria-modal="true" aria-labelledby="customer-form-title" @keydown.escape.window="formOpen && closeForm()">
                <div class="fixed inset-0 bg-neutral-900/40 backdrop-blur-xs" @click="closeForm()"></div>
                <form
                    method="POST"
                    :action="form && form.update_url ? form.update_url : {{ Js::from(route('user.commerce.customers.store')) }}"
                    class="relative w-full max-w-lg rounded-xl bg-neutral-0 shadow-2xl"
                    x-show="formOpen"
                    x-transition
                >
                    @csrf
                    <template x-if="form && form.update_url">
                        <input type="hidden" name="_method" value="PUT">
                    </template>
                    <input type="hidden" name="form_update_url" :value="form ? form.update_url : ''">

                    <div class="flex items-center justify-between border-b border-border-soft px-5 py-4">
                        <h3 id="customer-form-title" class="text-base font-semibold text-title" x-text="form && form.update_url ? {{ Js::from(__('Edit customer')) }} : {{ Js::from(__('Add customer')) }}"></h3>
                        <button type="button" @click="closeForm()" class="rounded-md p-1.5 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 transition">
                            <i class="ph ph-x text-lg"></i>
                        </button>
                    </div>

                    <template x-if="form">
                        <div class="space-y-4 p-5">
                            @if($formErrors->any())
                                <div class="rounded-lg border border-error/30 bg-error/5 p-3 text-xs text-error">
                                    <ul class="list-disc space-y-0.5 pl-4">
                                        @foreach($formErrors->all() as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div>
                                <label for="customer-name" class="form-label">{{ __('Full name') }} <span class="text-error">*</span></label>
                                <input id="customer-name" type="text" name="name" x-model="form.name" maxlength="150" required class="form-input w-full">
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="customer-phone" class="form-label">{{ __('Phone / WhatsApp') }} <span class="text-error">*</span></label>
                                    <x-ui.phone-input id="customer-phone" name="phone" x-model="form.phone" :required="true" />
                                </div>
                                <div>
                                    <label for="customer-email" class="form-label">{{ __('Email') }}</label>
                                    <input id="customer-email" type="email" name="email" x-model="form.email" maxlength="255" class="form-input w-full">
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="customer-password" class="form-label">{{ __('Password') }}</label>
                                    <input id="customer-password" type="password" name="password" minlength="8" autocomplete="new-password" class="form-input w-full">
                                </div>
                                <div>
                                    <label for="customer-password-confirmation" class="form-label">{{ __('Confirm password') }}</label>
                                    <input id="customer-password-confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" class="form-input w-full">
                                </div>
                                <p class="text-[11px] text-neutral-500 sm:col-span-2" x-text="form.update_url ? {{ Js::from(__('Leave blank to keep the current password.')) }} : {{ Js::from(__('Optional. Without a password the customer signs in with a WhatsApp code.')) }}"></p>
                            </div>

                            <div class="space-y-3 rounded-lg border border-border-soft p-4">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ __('Default shipping address') }}</h4>
                                <div>
                                    <label for="customer-line1" class="form-label">{{ __('Address line 1') }}</label>
                                    <input id="customer-line1" type="text" name="address[line1]" x-model="form.address.line1" maxlength="255" class="form-input w-full">
                                </div>
                                <div>
                                    <label for="customer-line2" class="form-label">{{ __('Address line 2') }}</label>
                                    <input id="customer-line2" type="text" name="address[line2]" x-model="form.address.line2" maxlength="255" class="form-input w-full">
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="customer-city" class="form-label">{{ __('City') }}</label>
                                        <input id="customer-city" type="text" name="address[city]" x-model="form.address.city" maxlength="120" :required="form.address.line1 !== ''" class="form-input w-full">
                                    </div>
                                    <div>
                                        <label for="customer-state" class="form-label">{{ __('State / Region') }}</label>
                                        <input id="customer-state" type="text" name="address[state]" x-model="form.address.state" maxlength="120" class="form-input w-full">
                                    </div>
                                    <div>
                                        <label for="customer-postal" class="form-label">{{ __('Postal code') }}</label>
                                        <input id="customer-postal" type="text" name="address[postal_code]" x-model="form.address.postal_code" maxlength="30" class="form-input w-full">
                                    </div>
                                    <div>
                                        <label for="customer-country" class="form-label">{{ __('Country') }}</label>
                                        <select id="customer-country" name="address[country]" x-model="form.address.country" class="form-input w-full">
                                            <option value="">{{ __('Select country') }}</option>
                                            @foreach($countryOptions as $iso => $option)
                                                <option value="{{ $iso }}">{{ $option['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="flex items-center justify-end gap-2 border-t border-border-soft bg-neutral-50/50 px-5 py-4">
                        <button type="button" @click="closeForm()" class="btn btn-sm btn-outline">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-sm btn-primary inline-flex items-center gap-1.5">
                            <i class="ph ph-floppy-disk"></i>
                            <span x-text="form && form.update_url ? {{ Js::from(__('Save changes')) }} : {{ Js::from(__('Add customer')) }}"></span>
                        </button>
                    </div>
                </form>
            </div>
        @endcan
    </div>
</x-layouts.user>
