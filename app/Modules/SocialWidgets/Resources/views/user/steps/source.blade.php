<x-social-widgets::accordion section="source" icon="ph-user" :title="__('Connect Instagram')">
    <form class="flex gap-2" x-on:submit.prevent="searchSource()">
        <label class="relative flex-1">
            <i class="ph ph-instagram-logo pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-body"></i>
            <input type="text" class="form-input w-full pl-9 text-sm" placeholder="{{ __('Account username (e.g., @yourbrand)') }}" x-model="sourceUsername" aria-label="{{ __('Instagram username') }}">
        </label>
        <button type="submit" class="btn-sm btn-outline" x-bind:disabled="isSearching">
            <span x-show="!isSearching">{{ __('Search') }}</span>
            <i class="ph ph-spinner animate-spin" x-show="isSearching" x-cloak></i>
        </button>
    </form>
    <p class="mt-2 text-xs text-warning" x-show="sourceMessage" x-text="sourceMessage" x-cloak></p>

    @if ($account)
        <div class="mt-4 flex items-center gap-3 rounded-md border border-success/30 bg-success/10 px-3 py-2.5">
            <i class="ph-fill ph-check-circle text-lg text-success"></i>
            <div class="min-w-0 text-sm">
                <p class="truncate font-semibold text-title">{{ $account->name }}</p>
                <p class="text-xs text-body">{{ __('Connected — your posts sync automatically.') }}</p>
            </div>
        </div>
    @elseif ($connectUrl)
        <a href="{{ $connectUrl }}" class="btn mt-4 w-full rounded-md bg-title text-white hover:bg-title/90">{{ __('Connect Instagram') }}</a>
        <p class="mt-3 text-xs text-body">{{ __('Connect your Instagram business account for real posts and username search. Until then the preview shows sample posts.') }}</p>
    @endif
</x-social-widgets::accordion>
