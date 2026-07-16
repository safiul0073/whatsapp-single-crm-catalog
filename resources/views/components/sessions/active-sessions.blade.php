@props([
    'sessions',
    'revokeRoute',
    'revokeAllRoute',
])

<div class="section-card">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <i class="ph ph-devices text-xl"></i>
            </div>
            <div>
                <h3 class="heading-5 text-neutral-950">{{ __('Active Sessions') }}</h3>
                <p class="text-sm text-neutral-400">{{ __('Manage and revoke your active sessions on other browsers and devices.') }}</p>
            </div>
        </div>

        @if($sessions->where('is_current', false)->count() > 0)
            <button type="button" class="btn btn-danger btn-sm" data-modal-trigger="confirmRevokeAllSessions">
                <i class="ph ph-sign-out mr-1.5"></i>
                {{ __('Revoke All Others') }}
            </button>
        @endif
    </div>

    <div class="divide-y divide-neutral-100">
        @forelse($sessions as $session)
            <div class="flex items-center justify-between py-3">
                <div class="flex items-center gap-3">
                    {{-- Device icon --}}
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-neutral-100 text-neutral-500">
                        @if($session->device === 'Mobile')
                            <i class="ph ph-device-mobile text-xl"></i>
                        @elseif($session->device === 'Tablet')
                            <i class="ph ph-device-tablet text-xl"></i>
                        @else
                            <i class="ph ph-desktop text-xl"></i>
                        @endif
                    </div>

                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-neutral-950">{{ $session->browser }} {{ __('on') }} {{ $session->platform }}</span>
                            @if($session->is_current)
                                <x-ui.badge variant="success">{{ __('Current') }}</x-ui.badge>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 text-sm text-neutral-400">
                            <span>{{ $session->ip_address }}</span>
                            <span>&middot;</span>
                            <span>{{ $session->last_activity->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>

                @unless($session->is_current)
                    <button type="button" class="btn btn-outline btn-sm" data-modal-trigger="confirmRevokeSession-{{ $loop->index }}">
                        {{ __('Revoke') }}
                    </button>
                @endunless
            </div>

            @unless($session->is_current)
                <x-ui.confirm
                    :id="'confirmRevokeSession-' . $loop->index"
                    :title="__('Revoke Session?')"
                    :message="__('Are you sure you want to revoke this session? The device will be signed out immediately.')"
                    :confirmText="__('Yes, Revoke')"
                    :formId="'revoke-session-' . $loop->index"
                />

                <form id="revoke-session-{{ $loop->index }}" method="POST" action="{{ route($revokeRoute, $session->id) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endunless
        @empty
            <div class="py-4 text-center text-sm text-neutral-400">
                {{ __('No active sessions found.') }}
            </div>
        @endforelse
    </div>
</div>

{{-- Revoke All Others Confirm Modal --}}
@if($sessions->where('is_current', false)->count() > 0)
    <x-ui.confirm
        id="confirmRevokeAllSessions"
        :title="__('Revoke All Other Sessions?')"
        :message="__('Are you sure you want to revoke all other sessions? All other devices will be signed out immediately.')"
        :confirmText="__('Yes, Revoke All')"
        formId="revoke-all-sessions"
    />

    <form id="revoke-all-sessions" method="POST" action="{{ route($revokeAllRoute) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif
