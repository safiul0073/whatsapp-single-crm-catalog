@php
    $activeLanguages = $activeLanguages ?? collect();
    $currentLocale = $currentLocale ?? app()->getLocale();
    $currentLang = $activeLanguages->firstWhere('code', $currentLocale);
@endphp

@if($activeLanguages->count() > 1)
<div class="dropdown-wrapper relative">
    <button type="button"
            class="flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-sm text-neutral-500 transition-colors hover:bg-neutral-50"
            data-action="toggle-dropdown"
            data-target="languageDropdown"
            aria-haspopup="true"
            aria-label="Switch language">
        <i class="ph ph-translate text-lg"></i>
        <span class="hidden lg:inline">{{ $currentLang?->native_name ?? $currentLocale }}</span>
        <i class="ph ph-caret-down text-xs text-neutral-400"></i>
    </button>

    <div id="languageDropdown"
         class="dropdown-panel bg-neutral-0 absolute end-0 top-full z-50 mt-2 w-48 overflow-hidden rounded-xl border border-neutral-100 shadow-lg"
         role="menu">
        <div class="py-1">
            @foreach($activeLanguages as $lang)
                <form method="POST" action="{{ route('locale.switch') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $lang->code }}">
                    <button type="submit"
                            class="flex w-full items-center gap-3 px-4 py-2 text-sm transition-colors hover:bg-neutral-50 {{ $lang->code === $currentLocale ? 'text-primary font-medium bg-primary/5' : 'text-neutral-600' }}"
                            role="menuitem">
                        <span class="min-w-[2rem] text-xs uppercase text-neutral-400">{{ $lang->code }}</span>
                        {{ $lang->native_name }}
                        @if($lang->code === $currentLocale)
                            <i class="ph ph-check ms-auto text-primary"></i>
                        @endif
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</div>
@endif
