@props(['section', 'icon', 'title'])

<div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
    <button type="button" class="flex w-full items-center gap-2.5 px-4 py-3.5 text-left" x-on:click="toggleSection('{{ $section }}')" x-bind:aria-expanded="openSection === '{{ $section }}'">
        <span class="grid h-6 w-6 place-items-center rounded-md bg-section text-body"><i class="ph {{ $icon }} text-sm"></i></span>
        <span class="flex-1 font-mono text-[11px] font-bold uppercase tracking-[0.12em] text-title">{{ $title }}</span>
        <i class="ph ph-caret-down text-sm text-body transition" x-bind:class="openSection === '{{ $section }}' && 'rotate-180'"></i>
    </button>
    <div class="border-t border-neutral-100 bg-section/40 px-4 py-4" x-show="openSection === '{{ $section }}'" x-cloak>
        {{ $slot }}
    </div>
</div>
