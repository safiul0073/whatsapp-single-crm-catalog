<x-layouts.admin :title="__('Payment Detail')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Payment') }} <span class="text-neutral-400">#{{ Str::limit($payment->uuid, 8) }}</span></h1>
            <x-ui.button variant="outline" href="{{ route('admin.payments.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Payment Details --}}
            <div class="section-card space-y-4">
                <h3 class="text-base font-semibold text-neutral-900">{{ __('Payment Details') }}</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Amount') }}</p>
                        <p class="text-lg font-bold text-neutral-900 mt-1">{{ $payment->formatted_amount }}</p>
                        @if($payment->currency !== currency_default_code())
                            <p class="text-xs text-neutral-400 mt-1">{{ __('Default') }}: {{ $payment->formatted_default_amount }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Status') }}</p>
                        <div class="mt-1">
                            @switch($payment->status)
                                @case('completed') <x-ui.badge variant="success">{{ __('Completed') }}</x-ui.badge> @break
                                @case('pending') <x-ui.badge variant="warning">{{ __('Pending') }}</x-ui.badge> @break
                                @case('failed') <x-ui.badge variant="danger">{{ __('Failed') }}</x-ui.badge> @break
                                @default <x-ui.badge variant="neutral">{{ ucfirst($payment->status) }}</x-ui.badge>
                            @endswitch
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Gateway') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">{{ ucfirst($payment->gateway) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Gateway ID') }}</p>
                        <p class="text-sm text-neutral-900 mt-1 break-all">{{ $payment->gateway_payment_id ?? __('—') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Customer') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">{{ $payment->user?->name ?? $payment->user?->email ?? __('Guest') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Method') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">{{ $payment->payment_method ? ucfirst($payment->payment_method) : __('—') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Created') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">{{ format_date($payment->created_at, true) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Paid At') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">{{ $payment->paid_at ? format_date($payment->paid_at, true) : __('—') }}</p>
                    </div>
                </div>

                @if($payment->description)
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Description') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">{{ $payment->description }}</p>
                    </div>
                @endif

                @if(!empty($payment->metadata))
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider mb-1">{{ __('Metadata') }}</p>
                        <pre class="rounded-lg bg-neutral-50 dark:bg-neutral-900 p-3 text-xs text-neutral-700 dark:text-neutral-300 overflow-x-auto">{{ json_encode($payment->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                @endif
            </div>

            {{-- Right Panel --}}
            <div class="space-y-6">
                {{-- Manual Payment Approval --}}
                @if($payment->gateway === 'manual' && $payment->status === 'pending')
                    <div class="section-card space-y-4">
                        <h3 class="text-base font-semibold text-neutral-900">{{ __('Manual Payment Review') }}</h3>
                        <p class="text-sm text-neutral-500">{{ __('This payment requires manual verification. Review the payment proof and approve or reject.') }}</p>

                        @if(!empty($payment->metadata['proof_media_ids']))
                            <div>
                                <p class="text-xs text-neutral-400 uppercase tracking-wider mb-2">{{ __('Payment Proof') }}</p>
                                {{-- Display proof images/files --}}
                                @foreach((array) $payment->metadata['proof_media_ids'] as $mediaId)
                                    @if($mediaId)
                                        <img src="{{ media_url($mediaId) }}" alt="Payment Proof" class="rounded-lg max-w-full border border-neutral-200 mb-2">
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        <div class="flex items-center gap-3 pt-3 border-t border-neutral-100">
                            <form method="POST" action="{{ route('admin.payments.approve', $payment) }}">
                                @csrf
                                <x-ui.button type="submit" variant="primary">
                                    <i class="ph ph-check-circle"></i> {{ __('Approve Payment') }}
                                </x-ui.button>
                            </form>

                            <form method="POST" action="{{ route('admin.payments.reject', $payment) }}"
                                  x-data="{ showReason: false }">
                                @csrf
                                <div x-show="!showReason">
                                    <x-ui.button type="button" variant="outline" @click="showReason = true">
                                        <i class="ph ph-x-circle"></i> {{ __('Reject') }}
                                    </x-ui.button>
                                </div>
                                <div x-show="showReason" x-cloak class="space-y-2">
                                    <x-forms.textarea name="rejection_reason" rows="2" :placeholder="__('Reason for rejection (optional)')" />
                                    <x-ui.button type="submit" variant="outline" class="!text-danger !border-danger">
                                        <i class="ph ph-x-circle"></i> {{ __('Confirm Rejection') }}
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- Approval/Rejection Info --}}
                @if(!empty($payment->metadata['approved_by']))
                    <div class="section-card">
                        <div class="flex items-center gap-2 text-success">
                            <i class="ph ph-check-circle text-lg"></i>
                            <span class="text-sm font-semibold">{{ __('Approved') }}</span>
                        </div>
                        <p class="text-xs text-neutral-400 mt-1">{{ $payment->metadata['approved_at'] ?? '' }}</p>
                    </div>
                @endif

                @if(!empty($payment->metadata['rejected_by']))
                    <div class="section-card">
                        <div class="flex items-center gap-2 text-danger">
                            <i class="ph ph-x-circle text-lg"></i>
                            <span class="text-sm font-semibold">{{ __('Rejected') }}</span>
                        </div>
                        @if(!empty($payment->metadata['rejection_reason']))
                            <p class="text-sm text-neutral-600 mt-1">{{ $payment->metadata['rejection_reason'] }}</p>
                        @endif
                        <p class="text-xs text-neutral-400 mt-1">{{ $payment->metadata['rejected_at'] ?? '' }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
