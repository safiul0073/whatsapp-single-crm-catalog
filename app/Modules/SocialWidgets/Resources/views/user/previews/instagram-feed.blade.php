<div class="grid grid-cols-3 gap-1">
    @foreach (['bg-rose-300', 'bg-amber-200', 'bg-sky-300', 'bg-violet-300', 'bg-emerald-200', 'bg-orange-300'] as $tileColor)
        <span class="aspect-square rounded-sm {{ $tileColor }}"></span>
    @endforeach
</div>
