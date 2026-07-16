@if($isImpersonating ?? false)
@php
    $currentUserName = ($authUser ?? auth()->user())?->name ?? __('User');
    $stopRoute = \Illuminate\Support\Facades\Route::has('admin.impersonation.stop')
        ? route('admin.impersonation.stop')
        : '#';
@endphp
<div class="bg-warning/90 text-warning-900 fixed top-0 right-0 left-0 z-[60] py-2 px-4 text-center text-sm font-medium shadow-sm backdrop-blur-sm">
    <div class="flex items-center justify-center gap-2">
        <i class="ph ph-user-switch"></i>
        <span>
            {{ __(':name is impersonating :target', [
                'name' => $impersonator['name'] ?? __('Admin'),
                'target' => $currentUserName,
            ]) }}
        </span>
        <form method="POST" action="{{ $stopRoute }}" class="inline">
            @csrf
            <button type="submit" class="ms-3 font-bold underline hover:no-underline">
                {{ __('Return to Admin') }}
            </button>
        </form>
    </div>
</div>
{{-- Push body content down to account for the fixed banner --}}
<div class="h-10"></div>
@endif
