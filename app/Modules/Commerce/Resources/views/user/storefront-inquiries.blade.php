{{-- =====================================================================
     TAB: CUSTOMER INQUIRIES & CONTACT MESSAGES
     ===================================================================== --}}
<div
    x-show="activeTab === 'inquiries'"
    x-cloak
    class="space-y-6"
    x-data="{
        filterType: 'all',
        copiedEmail: null,
        copyEmail(email) {
            navigator.clipboard.writeText(email);
            this.copiedEmail = email;
            setTimeout(() => { if (this.copiedEmail === email) this.copiedEmail = null; }, 2000);
        }
    }"
>
    {{-- Header Banner & Stats --}}
    <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-neutral-100 pb-5 dark:border-neutral-800">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20">
                    <i class="ph ph-chats-circle text-2xl"></i>
                </span>
                <div>
                    <h2 class="heading-4 text-title text-base font-semibold">{{ __('Customer Inquiries & Contact Messages') }}</h2>
                    <p class="text-xs text-body">{{ __('Messages submitted through your storefront Contact Us page, order assistance requests, and newsletter signups.') }}</p>
                </div>
            </div>

            {{-- Stat Badges --}}
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-1.5 text-xs font-semibold text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                    <i class="ph ph-envelope-simple text-sm text-primary"></i>
                    <span>{{ $inquiries->where('type', 'contact')->count() }} {{ __('Messages') }}</span>
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-1.5 text-xs font-semibold text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                    <i class="ph ph-newspaper text-sm text-emerald-600"></i>
                    <span>{{ $inquiries->where('type', 'subscriber')->count() }} {{ __('Subscribers') }}</span>
                </span>
            </div>
        </div>

        {{-- Filters & Controls --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-1.5 rounded-xl border border-neutral-200 bg-neutral-50/70 p-1 dark:border-neutral-800 dark:bg-neutral-800/50">
                <button
                    type="button"
                    @click="filterType = 'all'"
                    :class="filterType === 'all'
                        ? 'bg-white text-neutral-900 shadow-2xs dark:bg-neutral-700 dark:text-white font-semibold'
                        : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                    class="rounded-lg px-3 py-1.5 text-xs transition-all"
                >
                    {{ __('All Submissions') }} ({{ $inquiries->count() }})
                </button>
                <button
                    type="button"
                    @click="filterType = 'contact'"
                    :class="filterType === 'contact'
                        ? 'bg-white text-neutral-900 shadow-2xs dark:bg-neutral-700 dark:text-white font-semibold'
                        : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                    class="rounded-lg px-3 py-1.5 text-xs transition-all"
                >
                    {{ __('Contact Messages') }} ({{ $inquiries->where('type', 'contact')->count() }})
                </button>
                <button
                    type="button"
                    @click="filterType = 'subscriber'"
                    :class="filterType === 'subscriber'
                        ? 'bg-white text-neutral-900 shadow-2xs dark:bg-neutral-700 dark:text-white font-semibold'
                        : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                    class="rounded-lg px-3 py-1.5 text-xs transition-all"
                >
                    {{ __('Newsletter Subscribers') }} ({{ $inquiries->where('type', 'subscriber')->count() }})
                </button>
            </div>

            <div class="text-xs text-neutral-500">
                <i class="ph ph-bell-simple text-amber-500 inline mr-1"></i>
                {{ __('New inquiries trigger instant in-app alerts in your dashboard.') }}
            </div>
        </div>

        {{-- Messages & Inquiries Stream --}}
        <div class="space-y-4">
            @forelse($inquiries as $inquiry)
                @php
                    $isContact = ($inquiry->type === 'contact');
                    $data = (array) ($inquiry->data ?? []);
                    $name = $data['name'] ?? ($isContact ? __('Anonymous Customer') : __('Subscriber'));
                    $email = $data['email'] ?? '';
                    $phone = $data['phone'] ?? null;
                    $subject = $data['subject'] ?? ($isContact ? __('General Inquiry') : __('Newsletter Signup'));
                    $orderRef = $data['order_ref'] ?? null;
                    $message = $data['message'] ?? null;
                    $cleanPhone = $phone ? preg_replace('/[^0-9]/', '', $phone) : null;
                @endphp

                <div
                    x-show="filterType === 'all' || filterType === '{{ $inquiry->type }}'"
                    class="rounded-xl border border-neutral-200 bg-neutral-50/40 p-5 transition-all hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-800/30 dark:hover:border-neutral-700 space-y-4"
                >
                    {{-- Top Row: Identity, Type Tag, Date, and Actions --}}
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            {{-- Avatar Circle --}}
                            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $isContact ? 'bg-primary/10 text-primary dark:bg-primary/20' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' }} font-bold text-sm">
                                {{ strtoupper(substr($name, 0, 1) ?: 'C') }}
                            </div>

                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold text-sm text-neutral-900 dark:text-white">
                                        {{ $name }}
                                    </h3>

                                    @if($isContact)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                            <i class="ph ph-chat-circle-dots"></i>
                                            {{ $subject }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                            <i class="ph ph-paper-plane-tilt"></i>
                                            {{ __('Newsletter Subscriber') }}
                                        </span>
                                    @endif

                                    @if($orderRef)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-mono font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                            <i class="ph ph-hash"></i>
                                            {{ __('Order') }}: {{ $orderRef }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Contact Metadata --}}
                                <div class="flex flex-wrap items-center gap-3 text-xs text-neutral-500 dark:text-neutral-400">
                                    @if($email)
                                        <div class="flex items-center gap-1 font-mono">
                                            <i class="ph ph-envelope text-neutral-400"></i>
                                            <a href="mailto:{{ $email }}" class="text-primary hover:underline">{{ $email }}</a>
                                            <button
                                                type="button"
                                                @click="copyEmail('{{ $email }}')"
                                                class="ml-1 text-neutral-400 hover:text-neutral-700 dark:hover:text-white"
                                                title="{{ __('Copy email') }}"
                                            >
                                                <i class="ph" :class="copiedEmail === '{{ $email }}' ? 'ph-check text-emerald-500' : 'ph-copy'"></i>
                                            </button>
                                        </div>
                                    @endif

                                    @if($phone)
                                        <div class="flex items-center gap-1 font-mono">
                                            <i class="ph ph-phone text-neutral-400"></i>
                                            <a href="tel:{{ $phone }}" class="hover:underline">{{ $phone }}</a>
                                        </div>
                                    @endif

                                    <div class="flex items-center gap-1 text-[11px]">
                                        <i class="ph ph-clock text-neutral-400"></i>
                                        <span title="{{ $inquiry->created_at?->toDayDateTimeString() }}">
                                            {{ $inquiry->created_at?->diffForHumans() }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Quick Action Buttons --}}
                        <div class="flex items-center gap-2 self-end sm:self-auto shrink-0">
                            @if($email)
                                <a
                                    href="mailto:{{ $email }}?subject={{ rawurlencode('Re: ' . ($subject ?: 'Store Inquiry')) }}"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-2xs hover:bg-neutral-50 hover:text-primary dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700"
                                >
                                    <i class="ph ph-arrow-bend-up-left text-sm text-primary"></i>
                                    <span>{{ __('Reply Email') }}</span>
                                </a>
                            @endif

                            @if($cleanPhone)
                                <a
                                    href="https://wa.me/{{ $cleanPhone }}?text={{ rawurlencode('Hello ' . $name . ', thanks for contacting us regarding your inquiry.') }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-2xs hover:bg-emerald-100 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-900/50"
                                >
                                    <i class="ph ph-whatsapp-logo text-sm text-emerald-600"></i>
                                    <span>{{ __('WhatsApp') }}</span>
                                </a>
                            @endif

                            {{-- Delete Action --}}
                            <form
                                method="POST"
                                action="{{ route('user.commerce.storefront.contents.destroy', $inquiry->id) }}"
                                onsubmit="return confirm('{{ __('Are you sure you want to delete this inquiry?') }}')"
                                class="inline"
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 bg-white text-neutral-400 hover:border-red-300 hover:text-red-600 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:border-red-800 dark:hover:text-red-400"
                                    title="{{ __('Delete submission') }}"
                                >
                                    <i class="ph ph-trash text-sm"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Message Body (for Contact form submissions) --}}
                    @if($message)
                        <div class="rounded-xl border border-neutral-200/80 bg-white p-4 text-xs leading-relaxed text-neutral-800 shadow-2xs dark:border-neutral-700/80 dark:bg-neutral-900 dark:text-neutral-200 whitespace-pre-wrap">
                            {{ $message }}
                        </div>
                    @endif
                </div>
            @empty
                {{-- Empty State --}}
                <div class="rounded-2xl border border-dashed border-neutral-300 p-12 text-center text-xs text-neutral-500 dark:border-neutral-700 space-y-3">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-neutral-100 text-neutral-400 mx-auto dark:bg-neutral-800">
                        <i class="ph ph-tray text-2xl"></i>
                    </span>
                    <div class="space-y-1">
                        <p class="font-bold text-sm text-neutral-800 dark:text-neutral-200">{{ __('No customer inquiries yet') }}</p>
                        <p class="text-neutral-500 max-w-md mx-auto">
                            {{ __('When visitors submit messages via your storefront Contact Us page or join your newsletter, their full submissions will appear here with one-click reply options.') }}
                        </p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>
