<div class="flex items-center justify-end gap-3 lg:justify-start rtl:justify-start">
    @if($record->avatar && avatar_url($record->avatar))
        <img src="{{ avatar_url($record->avatar) }}" alt="{{ $record->name }}" class="h-10 w-10 rounded-full object-cover" />
    @else
        <div class="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-full font-bold">
            {{ strtoupper(substr($record->name, 0, 1)) }}
        </div>
    @endif
    <div class="text-right lg:text-left">
        <p class="text-sm font-bold text-neutral-950">{{ $record->name }}</p>
    </div>
</div>
