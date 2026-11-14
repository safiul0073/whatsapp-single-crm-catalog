@forelse($customers as $customer)
    @php
        $contact = $customer->contact;
        $name = $contact?->name ?: __('Guest Customer');
        $phone = $customer->verified_phone ?: ($contact?->phone ?: '—');
        $email = $contact?->email;
        $initial = mb_substr($name, 0, 1);

        $defaultAddr = $customer->addresses->firstWhere('is_default', true) ?? $customer->addresses->first();
        $editPayload = [
            'id' => $customer->id,
            'update_url' => route('user.commerce.customers.update', $customer),
            'name' => $contact?->name ?? '',
            'phone' => $contact?->phone ?? ($customer->verified_phone ?? ''),
            'email' => $email ?? '',
            'address' => [
                'line1' => $defaultAddr?->address['line1'] ?? '',
                'line2' => $defaultAddr?->address['line2'] ?? '',
                'city' => $defaultAddr?->address['city'] ?? '',
                'state' => $defaultAddr?->address['state'] ?? '',
                'postal_code' => $defaultAddr?->address['postal_code'] ?? '',
                'country' => $defaultAddr?->address['country'] ?? ($contact?->country ?? ''),
            ],
        ];
        $addrCity = $defaultAddr?->address['city'] ?? $contact?->city;
        $addrCountry = $defaultAddr?->address['country'] ?? $contact?->country;

        $ordersCount = $customer->orders_count ?? 0;
        $contactSpend = $spending->get($customer->contact_id, collect());
        $spendSummary = $contactSpend->map(function ($row) {
            $currency = strtoupper((string) ($row->currency ?? 'USD'));
            $symbol = match ($currency) {
                'USD' => '$',
                'BDT' => '৳',
                'EUR' => '€',
                'GBP' => '£',
                'INR' => '₹',
                default => $currency . ' ',
            };
            return $symbol . number_format((float) $row->total_spent, 2);
        })->implode(', ');
    @endphp
    <tr class="hover:bg-neutral-50/70 transition-colors">
        {{-- Customer Identity --}}
        <td class="py-3 px-4">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary border border-primary/20 uppercase shrink-0">
                    {{ $initial }}
                </span>
                <div class="min-w-0">
                    <div class="font-medium text-title truncate" title="{{ $name }}">{{ $name }}</div>
                    <div class="text-xs text-neutral-500 font-mono flex items-center gap-1.5 mt-0.5">
                        <span>{{ $phone }}</span>
                        @if($email)
                            <span class="text-neutral-300">·</span>
                            <span class="truncate max-w-[160px]" title="{{ $email }}">{{ $email }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </td>

        {{-- Channel / Source --}}
        <td class="py-3 px-4">
            @if($customer->source === 'admin')
                <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                    <i class="ph ph-user-plus text-sm"></i>
                    <span>{{ __('Added by staff') }}</span>
                </span>
            @elseif($customer->source === 'mobile_app')
                <span class="inline-flex items-center gap-1.5 rounded-full border border-purple-200 bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">
                    <i class="ph ph-device-mobile text-sm"></i>
                    <span>{{ __('Mobile App') }}</span>
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                    <i class="ph ph-globe text-sm"></i>
                    <span>{{ __('Storefront') }}</span>
                </span>
            @endif
        </td>

        {{-- Status / Verification --}}
        <td class="py-3 px-4">
            @if($customer->verified_at)
                <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700" title="{{ __('Verified at') }}: {{ $customer->verified_at->format('M j, Y') }}">
                    <i class="ph ph-check-circle text-sm text-emerald-600"></i>
                    <span>{{ __('Verified') }}</span>
                </span>
            @else
                <span class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600">
                    <i class="ph ph-circle text-xs text-neutral-400"></i>
                    <span>{{ __('Unverified') }}</span>
                </span>
            @endif
        </td>

        {{-- Orders & Spend --}}
        <td class="py-3 px-4">
            <div class="text-xs">
                @if($ordersCount > 0)
                    <div class="font-semibold text-title">
                        {{ trans_choice(':count order|:count orders', $ordersCount, ['count' => $ordersCount]) }}
                    </div>
                    <div class="text-emerald-700 font-medium mt-0.5">
                        {{ $spendSummary ?: __('No paid total') }}
                    </div>
                @else
                    <span class="text-neutral-400">{{ __('No orders yet') }}</span>
                @endif
            </div>
        </td>

        {{-- Default Address --}}
        <td class="py-3 px-4">
            @if($addrCity || $addrCountry)
                <div class="text-xs font-medium text-title">
                    {{ implode(', ', array_filter([$addrCity, $addrCountry])) }}
                </div>
                @if($defaultAddr && !empty($defaultAddr->address['line1']))
                    <div class="text-[11px] text-neutral-400 truncate max-w-[180px]" title="{{ $defaultAddr->address['line1'] }}">
                        {{ $defaultAddr->address['line1'] }}
                    </div>
                @endif
            @else
                <span class="text-neutral-300">—</span>
            @endif
        </td>

        {{-- Registered Date --}}
        <td class="py-3 px-4">
            <div class="text-xs font-medium text-title" title="{{ $customer->registered_at?->format('M j, Y · g:i A') ?? $customer->created_at->format('M j, Y · g:i A') }}">
                {{ ($customer->registered_at ?? $customer->created_at)->diffForHumans() }}
            </div>
            <div class="text-[11px] text-neutral-400">
                {{ ($customer->registered_at ?? $customer->created_at)->format('M j, Y') }}
            </div>
        </td>

        {{-- Actions --}}
        <td class="py-3 px-4 text-right">
            <div class="flex items-center justify-end gap-1.5">
                {{-- View Customer Orders --}}
                @if($customer->contact_id)
                    <a href="{{ route('user.commerce.orders.index', ['contact_id' => $customer->contact_id]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-neutral-200 bg-neutral-0 text-neutral-600 hover:border-primary hover:text-primary transition shadow-2xs" title="{{ __('View customer orders') }}">
                        <i class="ph ph-shopping-bag text-base"></i>
                        <span class="sr-only">{{ __('View orders') }}</span>
                    </a>
                @endif

                {{-- Open Details Drawer --}}
                <button
                    type="button"
                    @click="openDetails({{ Js::from([
                        'id' => $customer->id,
                        'name' => $name,
                        'phone' => $phone,
                        'email' => $email,
                        'verified_at' => $customer->verified_at?->format('M j, Y · g:i A'),
                        'registered_at' => ($customer->registered_at ?? $customer->created_at)->format('M j, Y · g:i A'),
                        'source' => $customer->source,
                        'channel_label' => $customer->channelLabel(),
                        'contact_id' => $customer->contact_id,
                        'edit' => $editPayload,
                        'orders_count' => $ordersCount,
                        'spend_summary' => $spendSummary,
                        'addresses' => $customer->addresses->map(fn($a) => [
                            'label' => $a->label,
                            'is_default' => $a->is_default,
                            'address' => $a->address,
                        ])->values()->all(),
                    ]) }})"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-neutral-200 bg-neutral-0 text-neutral-600 hover:border-primary hover:text-primary transition shadow-2xs"
                    title="{{ __('View customer details') }}"
                >
                    <i class="ph ph-eye text-base"></i>
                    <span class="sr-only">{{ __('View details') }}</span>
                </button>

                @can('commerce.customers.manage')
                    <button
                        type="button"
                        @click="openForm({{ Js::from($editPayload) }})"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-neutral-200 bg-neutral-0 text-neutral-600 hover:border-primary hover:text-primary transition shadow-2xs"
                        title="{{ __('Edit customer') }}"
                    >
                        <i class="ph ph-pencil-simple text-base"></i>
                        <span class="sr-only">{{ __('Edit customer') }}</span>
                    </button>

                    <form method="POST" action="{{ route('user.commerce.customers.destroy', $customer) }}" class="inline-flex">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-neutral-200 bg-neutral-0 text-neutral-600 hover:border-error hover:text-error transition shadow-2xs"
                            title="{{ __('Delete customer') }}"
                            data-confirm
                            data-confirm-title="{{ __('Delete customer?') }}"
                            data-confirm-body="{{ __(':name will lose their store login and saved addresses. Their contact record and past orders are kept.', ['name' => $name]) }}"
                            data-confirm-label="{{ __('Delete') }}"
                            data-confirm-variant="error"
                        >
                            <i class="ph ph-trash text-base"></i>
                            <span class="sr-only">{{ __('Delete customer') }}</span>
                        </button>
                    </form>
                @endcan
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="p-12 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-neutral-100 text-neutral-400 mb-3">
                <i class="ph ph-users text-2xl"></i>
            </div>
            <h3 class="text-base font-semibold text-title">{{ __('No customers found') }}</h3>
            <p class="mt-1 text-sm text-body max-w-sm mx-auto">
                @if(request()->filled('search'))
                    {{ __('No customers matched your search query. Try searching with a different name, phone, or email.') }}
                @else
                    {{ __('Customers who register or sign in on the Storefront or Mobile App appear here automatically, or add one yourself.') }}
                @endif
            </p>
            @if(request()->filled('search'))
                <div class="mt-4">
                    <a href="{{ route('user.commerce.customers.index') }}" @click.prevent="clearSearch()" class="btn btn-sm btn-outline inline-flex items-center gap-1.5">
                        <i class="ph ph-arrow-counter-clockwise"></i>
                        <span>{{ __('Clear search') }}</span>
                    </a>
                </div>
            @endif
        </td>
    </tr>
@endforelse
