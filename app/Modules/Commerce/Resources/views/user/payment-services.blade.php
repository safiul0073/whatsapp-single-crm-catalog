<x-layouts.user :title="__('Payment Services')">
    <div
        class="space-y-5"
        x-data="{
            methods: {{ Illuminate\Support\Js::from(old('payment_methods', $paymentMethods)) }},
            defaults: {{ Illuminate\Support\Js::from($defaultPaymentMethods) }},
            editingIndex: null,
            init() {
                this.methods = this.methods.map(method => ({
                    ...method,
                    fields: method.fields || []
                }));
            },
            defaultDummyData() {
                return [
                    {
                        id: 'remitly',
                        name: 'Remitly',
                        active: '1',
                        sort_order: 0,
                        icon_url: '{{ asset('images/payment-services/remitly.svg') }}',
                        recipient_details: 'Account Name: Global Garments Export Ltd\nBank: Standard Chartered Bank\nAccount Number: 01-8492049-01\nBranch: Dhaka Main Branch, Bangladesh\nSwift/BIC: SCBLBDDX\nPhone: +880 1712 345678',
                        instructions: '1. Open Remitly and select send to Bangladesh.\n2. Choose Bank Deposit and enter the details above.\n3. Send the exact order total amount.\n4. Enter your transaction ID and upload payment screenshot below.',
                        fields: [
                            { name: 'sender_name', label: 'Sender Full Name', required: '1' },
                            { name: 'sender_phone', label: 'Sender Phone Number', required: '0' }
                        ]
                    },
                    {
                        id: 'taptap-send',
                        name: 'Taptap Send',
                        active: '1',
                        sort_order: 1,
                        icon_url: '{{ asset('images/payment-services/taptap-send.svg') }}',
                        recipient_details: 'Recipient Name: Global Garments Export Ltd\nWallet / bKash Number: +880 1819 876543\nAccount Type: Merchant / Personal\nCountry: Bangladesh',
                        instructions: '1. Open the Taptap Send app on your mobile device.\n2. Select Bangladesh and enter the recipient mobile number above.\n3. Transfer the exact order total.\n4. Enter the transfer reference / transaction ID and upload screenshot below.',
                        fields: [
                            { name: 'sender_name', label: 'Sender Full Name', required: '1' }
                        ]
                    },
                    {
                        id: 'moneygram',
                        name: 'MoneyGram',
                        active: '1',
                        sort_order: 2,
                        icon_url: '{{ asset('images/payment-services/moneygram.svg') }}',
                        recipient_details: 'Receiver Name: MD SAFIUL ISLAM\nCountry: Bangladesh\nCity: Dhaka\nPhone: +880 1911 223344',
                        instructions: '1. Send money online or visit any MoneyGram agent location.\n2. Use the exact receiver name and country shown above.\n3. Enter the 8-digit reference number (MTCN) as Transaction ID and upload the receipt.',
                        fields: [
                            { name: 'sender_name', label: 'Sender Name', required: '1' },
                            { name: 'mtcn_number', label: '8-Digit MTCN', required: '1' }
                        ]
                    }
                ];
            },
            loadDummyData() {
                this.methods = this.defaultDummyData();
            },
            restoreDefaults() {
                for (const method of this.defaults) {
                    if (!this.methods.some(existing => existing.id === method.id)) {
                        this.methods.push(JSON.parse(JSON.stringify(method)));
                    }
                }
            },
            applyDefaults(method) {
                const template = this.defaults.find(item => item.id === method.id) ||
                    this.defaultDummyData().find(item => item.id === method.id);
                if (template) {
                    method.recipient_details = template.recipient_details;
                    method.instructions = template.instructions;
                    method.fields = JSON.parse(JSON.stringify(template.fields || []));
                }
            },
            addMethod() {
                this.methods.push({
                    id: 'gateway-' + crypto.randomUUID().slice(0, 8),
                    name: '',
                    recipient_details: '',
                    instructions: '',
                    active: '1',
                    sort_order: this.methods.length,
                    fields: []
                });
                this.editingIndex = this.methods.length - 1;
            },
            editMethod(index) {
                this.editingIndex = index;
            },
            closeModal() {
                this.editingIndex = null;
            },
            removeMethod(index) {
                if (confirm('{{ __('Remove this payment service?') }}')) {
                    this.methods.splice(index, 1);
                    if (this.editingIndex === index) {
                        this.editingIndex = null;
                    }
                }
            },
            toggleStatus(index) {
                this.methods[index].active = this.methods[index].active === '1' ? '0' : '1';
            }
        }"
    >
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-md bg-primary/10 text-primary">
                        <i class="ph ph-credit-card text-lg"></i>
                    </span>
                    <h1 class="heading-4 text-title">{{ __('Payment Services') }}</h1>
                </div>
                <p class="mt-1 text-xs text-body">
                    {{ __('Configure manual payment gateways (Remitly, Taptap Send, MoneyGram, and bank transfers). Customers view receiving accounts and submit payment receipts on checkout.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button variant="outline" href="{{ route('user.commerce.orders.settings') }}" class="rounded-sm text-xs py-1.5">
                    <i class="ph ph-gear"></i> {{ __('Order Settings') }}
                </x-ui.button>
                <x-ui.button variant="primary" type="button" @click="addMethod()" class="rounded-sm text-xs py-1.5">
                    <i class="ph ph-plus"></i> {{ __('Add Service') }}
                </x-ui.button>
            </div>
        </header>

        @if (session('success'))
            <div class="rounded-md border border-success/30 bg-success/10 p-3.5 text-xs text-success flex items-center justify-between" role="alert">
                <div class="flex items-center gap-2">
                    <i class="ph ph-check-circle text-base"></i>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md border border-error/30 bg-error/10 p-3.5 text-xs text-error" role="alert">
                <p class="font-semibold">{{ __('Please review the following errors:') }}</p>
                <ul class="mt-1.5 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" enctype="multipart/form-data" action="{{ route('user.commerce.payment-services.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <!-- Actions Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-neutral-200 bg-white p-3 shadow-2xs">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ __('Quick Presets') }}:</span>
                    <button
                        type="button"
                        @click="loadDummyData()"
                        class="inline-flex items-center gap-1.5 rounded-sm border border-primary/20 bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary hover:bg-primary/20 transition"
                        title="{{ __('Pre-fill Remitly, Taptap Send, and MoneyGram with realistic test account details') }}"
                    >
                        <i class="ph ph-flask"></i> {{ __('Load Sample Test Data') }}
                    </button>
                    <button
                        type="button"
                        @click="restoreDefaults()"
                        class="inline-flex items-center gap-1.5 rounded-sm border border-neutral-200 bg-neutral-50 px-2.5 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-100 transition"
                    >
                        <i class="ph ph-arrow-counter-clockwise"></i> {{ __('Restore Defaults') }}
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-neutral-500" x-text="`${methods.length} payment services configured`"></span>
                </div>
            </div>

            <!-- Payment Services Table -->
            <div class="rounded-md border border-neutral-200 bg-white overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-neutral-200 bg-neutral-50 text-neutral-600 font-semibold uppercase text-[11px] tracking-wider">
                            <tr>
                                <th scope="col" class="py-3 px-4 w-60">{{ __('Service / Gateway') }}</th>
                                <th scope="col" class="py-3 px-4 w-72">{{ __('Instructions & Account Details') }}</th>
                                <th scope="col" class="py-3 px-4 min-w-[200px]">{{ __('Customer Inputs') }}</th>
                                <th scope="col" class="py-3 px-4 w-28 text-center">{{ __('Status') }}</th>
                                <th scope="col" class="py-3 px-4 w-20 text-center">{{ __('Order') }}</th>
                                <th scope="col" class="py-3 px-4 w-24 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 bg-white">
                            <template x-for="(method, index) in methods" :key="method.id">
                                <tr class="hover:bg-neutral-50/60 transition items-center">
                                    <!-- Service Column -->
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="h-8 w-8 shrink-0 rounded-md border border-neutral-200 bg-neutral-50 flex items-center justify-center p-1 overflow-hidden">
                                                <template x-if="method.icon_url">
                                                    <img :src="method.icon_url" :alt="method.name" class="h-full w-full object-contain">
                                                </template>
                                                <template x-if="!method.icon_url">
                                                    <div class="h-full w-full rounded bg-primary/10 flex items-center justify-center text-primary font-bold text-xs" x-text="(method.name || 'P').charAt(0).toUpperCase()"></div>
                                                </template>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-neutral-900 text-sm truncate" x-text="method.name || 'Unnamed service'"></div>
                                                <div class="text-[11px] text-neutral-500 font-mono" x-text="method.id"></div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Instructions & Details Column (Modal Trigger) -->
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                @click="editMethod(index)"
                                                class="inline-flex items-center gap-1.5 rounded-sm border border-neutral-200 bg-neutral-50 px-2.5 py-1 text-xs font-medium text-neutral-700 hover:text-primary hover:border-primary/40 hover:bg-white transition"
                                                title="{{ __('View and edit account recipient details and instructions in modal') }}"
                                            >
                                                <i class="ph ph-sliders text-sm text-primary"></i>
                                                <span>{{ __('View / Edit Details') }}</span>
                                            </button>
                                            <template x-if="method.recipient_details">
                                                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-success" title="{{ __('Account details and instructions configured') }}">
                                                    <i class="ph ph-check-circle"></i> {{ __('Configured') }}
                                                </span>
                                            </template>
                                            <template x-if="!method.recipient_details">
                                                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-amber-500" title="{{ __('Missing recipient details') }}">
                                                    <i class="ph ph-warning"></i> {{ __('Pending') }}
                                                </span>
                                            </template>
                                        </div>
                                    </td>

                                    <!-- Customer Verification Fields Column -->
                                    <td class="py-3 px-4">
                                        <div class="flex flex-wrap gap-1">
                                            <template x-for="field in (method.fields || [])" :key="field.name">
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium border"
                                                    :class="field.required === '1' ? 'bg-primary/10 text-primary border-primary/20' : 'bg-neutral-50 text-neutral-600 border-neutral-200'"
                                                >
                                                    <span x-text="field.label"></span>
                                                    <span x-show="field.required === '1'" class="text-error font-bold">*</span>
                                                </span>
                                            </template>
                                            <template x-if="!method.fields || method.fields.length === 0">
                                                <span class="text-xs text-neutral-400">{{ __('None') }}</span>
                                            </template>
                                        </div>
                                    </td>

                                    <!-- Status Column -->
                                    <td class="py-3 px-4 text-center">
                                        <button
                                            type="button"
                                            @click="toggleStatus(index)"
                                            class="inline-flex items-center gap-1.5 rounded-sm px-2 py-0.5 text-xs font-medium transition"
                                            :class="method.active === '1' ? 'bg-success/15 text-success hover:bg-success/25' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'"
                                            :title="method.active === '1' ? 'Click to disable' : 'Click to enable'"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full" :class="method.active === '1' ? 'bg-success' : 'bg-neutral-400'"></span>
                                            <span x-text="method.active === '1' ? '{{ __('Enabled') }}' : '{{ __('Disabled') }}'"></span>
                                        </button>
                                    </td>

                                    <!-- Sort Order Column -->
                                    <td class="py-3 px-4 text-center font-mono text-xs text-neutral-600" x-text="method.sort_order"></td>

                                    <!-- Actions Column -->
                                    <td class="py-3 px-4 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <button
                                                type="button"
                                                @click="editMethod(index)"
                                                class="rounded-sm p-1.5 text-neutral-500 hover:text-primary hover:bg-primary/10 transition"
                                                title="{{ __('Edit service') }}"
                                            >
                                                <i class="ph ph-pencil-simple text-base"></i>
                                            </button>
                                            <button
                                                type="button"
                                                @click="removeMethod(index)"
                                                class="rounded-sm p-1.5 text-neutral-500 hover:text-error hover:bg-error/10 transition"
                                                title="{{ __('Remove service') }}"
                                            >
                                                <i class="ph ph-trash text-base"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <template x-if="methods.length === 0">
                                <tr>
                                    <td colspan="6" class="py-10 text-center">
                                        <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-md bg-neutral-100 text-neutral-400 mb-2">
                                            <i class="ph ph-credit-card text-xl"></i>
                                        </div>
                                        <p class="text-sm font-semibold text-neutral-800">{{ __('No payment services configured') }}</p>
                                        <p class="text-xs text-neutral-500 mt-0.5">{{ __('Click Load Sample Test Data or Add Service to configure manual payment gateways.') }}</p>
                                        <div class="mt-3 flex items-center justify-center gap-2">
                                            <button type="button" @click="loadDummyData()" class="rounded-sm bg-primary px-3 py-1 text-xs font-medium text-white hover:bg-primary-hover">
                                                {{ __('Load Sample Data') }}
                                            </button>
                                            <button type="button" @click="addMethod()" class="rounded-sm border border-neutral-200 bg-white px-3 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                                                {{ __('Add Service') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Hidden Form Inputs for State Submission -->
            <template x-for="(method, index) in methods" :key="method.id">
                <div>
                    <input type="hidden" :name="`payment_methods[${index}][id]`" :value="method.id">
                    <input type="hidden" :name="`payment_methods[${index}][name]`" :value="method.name">
                    <input type="hidden" :name="`payment_methods[${index}][recipient_details]`" :value="method.recipient_details">
                    <input type="hidden" :name="`payment_methods[${index}][instructions]`" :value="method.instructions">
                    <input type="hidden" :name="`payment_methods[${index}][active]`" :value="method.active">
                    <input type="hidden" :name="`payment_methods[${index}][sort_order]`" :value="method.sort_order">
                    <template x-if="method.remove_icon">
                        <input type="hidden" :name="`payment_methods[${index}][remove_icon]`" value="1">
                    </template>
                    <template x-for="(field, fIndex) in (method.fields || [])" :key="fIndex">
                        <div>
                            <input type="hidden" :name="`payment_methods[${index}][fields][${fIndex}][name]`" :value="field.name">
                            <input type="hidden" :name="`payment_methods[${index}][fields][${fIndex}][label]`" :value="field.label">
                            <input type="hidden" :name="`payment_methods[${index}][fields][${fIndex}][required]`" :value="field.required">
                        </div>
                    </template>
                </div>
            </template>

            <!-- Save Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-md border border-neutral-200 bg-white p-4 shadow-2xs">
                <div>
                    <h3 class="text-sm font-semibold text-neutral-900">{{ __('Ready to publish payment options?') }}</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">{{ __('Enabled payment services will appear immediately on the storefront checkout page for customers to select.') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-ui.button type="submit" variant="primary" class="rounded-sm px-5 py-2 text-xs font-medium">
                        <i class="ph ph-check"></i> {{ __('Save Payment Services') }}
                    </x-ui.button>
                </div>
            </div>

            <!-- Edit Modal Dialog -->
            <div
                x-show="editingIndex !== null"
                x-cloak
                class="fixed inset-0 z-50 overflow-y-auto bg-neutral-950/50 backdrop-blur-xs flex items-center justify-center p-4"
                @keydown.escape.window="closeModal()"
            >
                <div
                    x-show="editingIndex !== null"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    @click.away="closeModal()"
                    class="w-full max-w-3xl !rounded-md bg-white border border-neutral-200 shadow-xl overflow-hidden"
                >
                    <template x-if="editingIndex !== null && methods[editingIndex]">
                        <div class="flex flex-col max-h-[90vh]">
                            <!-- Modal Header -->
                            <div class="flex items-center justify-between border-b border-neutral-200 px-5 py-3.5 bg-neutral-50">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-8 w-8 rounded-md border border-neutral-200 bg-white flex items-center justify-center p-1 shadow-2xs">
                                        <template x-if="methods[editingIndex].icon_url">
                                            <img :src="methods[editingIndex].icon_url" class="h-full w-full object-contain">
                                        </template>
                                        <template x-if="!methods[editingIndex].icon_url">
                                            <i class="ph ph-credit-card text-primary text-base"></i>
                                        </template>
                                    </div>
                                    <h3 class="font-semibold text-sm text-neutral-900" x-text="methods[editingIndex].name ? methods[editingIndex].name : '{{ __('New Payment Service') }}'"></h3>
                                </div>
                                <button type="button" @click="closeModal()" class="rounded-sm p-1 text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100">
                                    <i class="ph ph-x text-base"></i>
                                </button>
                            </div>

                            <!-- Modal Body -->
                            <div class="space-y-4 p-5 overflow-y-auto flex-1">
                                <!-- Top Row: Basic Info -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                    <div>
                                        <label class="form-label text-xs mb-1">{{ __('Service Name') }} <span class="text-error">*</span></label>
                                        <input type="text" x-model="methods[editingIndex].name" class="form-input form-input-compact !rounded-[4px] text-xs !py-1.5" placeholder="e.g. Remitly, Taptap Send, Bank Deposit" required>
                                    </div>
                                    <div>
                                        <label class="form-label text-xs mb-1">{{ __('Service ID (Slug)') }} <span class="text-error">*</span></label>
                                        <input type="text" x-model="methods[editingIndex].id" class="form-input form-input-compact !rounded-[4px] font-mono text-xs !py-1.5" placeholder="e.g. remitly, taptap-send" required>
                                    </div>
                                </div>

                                <!-- Second Row: Status, Sort Order, Icon Upload -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                                    <div>
                                        <label class="form-label text-xs mb-1">{{ __('Status') }}</label>
                                        <select x-model="methods[editingIndex].active" class="form-input form-input-compact !rounded-[4px] text-xs !py-1.5">
                                            <option value="1">{{ __('Enabled') }}</option>
                                            <option value="0">{{ __('Disabled') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label text-xs mb-1">{{ __('Sort Order') }}</label>
                                        <input type="number" x-model.number="methods[editingIndex].sort_order" min="0" max="999" class="form-input form-input-compact !rounded-[4px] text-xs !py-1.5">
                                    </div>
                                    <div>
                                        <label class="form-label text-xs mb-1">{{ __('Service Icon / Logo') }}</label>
                                        <div class="flex items-center gap-2">
                                            <template x-if="methods[editingIndex].icon_url">
                                                <div class="flex items-center gap-1.5">
                                                    <img :src="methods[editingIndex].icon_url" class="h-7 w-7 object-contain !rounded-[4px] border border-neutral-200 bg-white p-0.5">
                                                    <label class="text-[11px] text-error cursor-pointer flex items-center gap-0.5">
                                                        <input type="checkbox" x-model="methods[editingIndex].remove_icon" value="1">
                                                        {{ __('Remove') }}
                                                    </label>
                                                </div>
                                            </template>
                                            <input
                                                type="file"
                                                :name="`payment_icons[${methods[editingIndex].id}]`"
                                                accept="image/png,image/jpeg,image/webp"
                                                class="form-input form-input-compact !rounded-[4px] text-xs flex-1 !py-1"
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- Recipient Details & Payment Instructions Row -->
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3.5">
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="form-label text-xs mb-0 font-medium text-neutral-800">{{ __('Recipient Details') }} <span class="text-error">*</span></label>
                                            <button
                                                type="button"
                                                @click="applyDefaults(methods[editingIndex])"
                                                class="inline-flex items-center gap-1 text-[11px] text-primary font-medium hover:underline"
                                            >
                                                <i class="ph ph-magic-wand"></i> {{ __('Use sample') }}
                                            </button>
                                        </div>
                                        <textarea
                                            x-model="methods[editingIndex].recipient_details"
                                            rows="5"
                                            class="form-input form-input-compact !rounded-[4px] font-mono text-xs leading-relaxed bg-white border border-neutral-200"
                                            placeholder="Account Name: ...&#10;Bank: ...&#10;Account Number: ..."
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="form-label text-xs mb-1 font-medium text-neutral-800">{{ __('Payment Instructions') }}</label>
                                        <textarea
                                            x-model="methods[editingIndex].instructions"
                                            rows="5"
                                            class="form-input form-input-compact !rounded-[4px] text-xs leading-relaxed bg-white border border-neutral-200"
                                            placeholder="1. Open app and choose recipient.&#10;2. Transfer exact amount.&#10;3. Enter transaction ID and upload screenshot."
                                        ></textarea>
                                    </div>
                                </div>

                                <!-- Customer Fields -->
                                <div class="pt-2 border-t border-neutral-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="form-label text-xs mb-0 font-medium text-neutral-800">{{ __('Customer Fields') }}</label>
                                        <button
                                            type="button"
                                            @click="methods[editingIndex].fields.push({ name: '', label: '', required: '1' })"
                                            class="inline-flex items-center gap-1 text-xs text-primary font-medium hover:underline"
                                        >
                                            <i class="ph ph-plus"></i> {{ __('Add Field') }}
                                        </button>
                                    </div>

                                    <div class="space-y-2">
                                        <template x-for="(field, fIdx) in methods[editingIndex].fields" :key="fIdx">
                                            <div class="grid grid-cols-12 items-center gap-2 !rounded-[4px] bg-neutral-50 border border-neutral-200 p-2">
                                                <div class="col-span-12 sm:col-span-5">
                                                    <input
                                                        type="text"
                                                        x-model="field.label"
                                                        placeholder="{{ __('Field Label (e.g. Sender Name)') }}"
                                                        class="form-input form-input-compact !rounded-[4px] text-xs !py-1.5 !px-3 border border-neutral-200 bg-white"
                                                        required
                                                    >
                                                </div>
                                                <div class="col-span-7 sm:col-span-4">
                                                    <input
                                                        type="text"
                                                        x-model="field.name"
                                                        placeholder="{{ __('Slug (e.g. sender_name)') }}"
                                                        class="form-input form-input-compact !rounded-[4px] text-xs font-mono !py-1.5 !px-3 border border-neutral-200 bg-white"
                                                        required
                                                    >
                                                </div>
                                                <div class="col-span-3 sm:col-span-2 flex items-center justify-center">
                                                    <label class="flex items-center gap-1.5 text-xs text-neutral-600 whitespace-nowrap cursor-pointer select-none">
                                                        <input type="checkbox" x-model="field.required" true-value="1" false-value="0" class="rounded border-neutral-300 text-primary">
                                                        <span>{{ __('Required') }}</span>
                                                    </label>
                                                </div>
                                                <div class="col-span-2 sm:col-span-1 flex items-center justify-end">
                                                    <button
                                                        type="button"
                                                        @click="methods[editingIndex].fields.splice(fIdx, 1)"
                                                        class="p-1.5 text-neutral-400 hover:text-error hover:bg-neutral-100 rounded transition"
                                                        title="{{ __('Delete field') }}"
                                                    >
                                                        <i class="ph ph-trash text-sm"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Footer -->
                            <div class="flex items-center justify-end gap-2 border-t border-neutral-200 px-5 py-3 bg-neutral-50">
                                <button type="button" @click="closeModal()" class="!rounded-[4px] border border-neutral-200 bg-white px-3.5 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-100 transition">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="button" @click="closeModal()" class="!rounded-[4px] bg-primary px-4 py-1.5 text-xs font-medium text-white hover:bg-primary-hover shadow-2xs transition">
                                    {{ __('Done') }}
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </form>
    </div>
</x-layouts.user>
