@props(['languages' => collect(), 'current' => null])

@if($languages->count() > 1)
    <div class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-white p-1" role="group" aria-label="{{ __('Language') }}">
        <i class="ph ph-translate ms-1.5 text-sm text-neutral-400" aria-hidden="true"></i>
        @foreach($languages as $language)
            <form method="POST" action="{{ route('locale.switch') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $language->code }}">
                <button
                    type="submit"
                    lang="{{ $language->code }}"
                    aria-pressed="{{ $language->code === $current ? 'true' : 'false' }}"
                    @class([
                        'rounded-full px-3 py-1 text-xs font-semibold transition-colors',
                        'bg-primary text-white' => $language->code === $current,
                        'text-neutral-600 hover:bg-neutral-100' => $language->code !== $current,
                    ])
                >
                    {{ $language->native_name }}
                </button>
            </form>
        @endforeach
    </div>
@endif
