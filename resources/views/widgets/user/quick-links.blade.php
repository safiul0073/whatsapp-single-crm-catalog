<div class="section-card">
    <h2 class="heading-5 text-neutral-950 mb-4">{{ __('Quick Links') }}</h2>
    <div class="flex flex-wrap gap-2">
        <x-ui.button variant="soft-primary" href="{{ route('user.profile.edit') }}">
            <i class="ph ph-user-circle"></i> {{ __('Edit Profile') }}
        </x-ui.button>
        <x-ui.button variant="soft-primary" href="{{ route('user.system-notifications.index') }}">
            <i class="ph ph-bell"></i> {{ __('Notifications') }}
        </x-ui.button>
    </div>
</div>
