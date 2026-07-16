@php
    $page = (object) [
        'meta_title' => 'Frequently Asked Questions — ' . ($themeVars['logo_text'] ?? config('app.name')),
        'title' => 'Frequently Asked Questions',
        'meta_description' => 'Answers about Classic process, engagement models, ownership, and support.',
    ];
@endphp

@extends('frontend.themes.classic.layouts.page')

@section('title', $page->meta_title)

@section('meta_description', $page->meta_description)

@section('main')
    <section class="relative overflow-hidden isolate py-[clamp(72px,9vw,120px)] bg-white" aria-labelledby="faq-page-heading" id="faqs">
        <div class="absolute inset-0 pointer-events-none faq2-bg-grid" aria-hidden="true"></div>
        <div class="relative mx-auto px-6 max-w-site">
            <div class="grid grid-cols-[minmax(min(300px,100%),420px)_minmax(0,1fr)] gap-[clamp(40px,5vw,80px)] items-start faq2-layout">
                <aside class="sticky top-24 max-h-[calc(100vh-120px)] overflow-y-auto scrollbar-none flex flex-col gap-[18px] faq2-side-sticky">
                    <div>
                        <span class="inline-flex items-center gap-2 font-mono text-[11.5px] font-semibold uppercase tracking-[0.16em] text-brand-blue">
                            <span class="w-1.5 h-1.5 rounded-pill bg-brand-blue shadow-faq-eyebrow-dot"></span>FAQ Guide
                        </span>
                        <h1 id="faq-page-heading" class="mt-5 font-display text-[clamp(34px,5vw,62px)] font-extrabold tracking-[-0.035em] leading-[0.95] text-brand-navy-ink">
                            <span>Questions teams ask</span><span class="faq2-h2-accent"> before they hire us.</span>
                        </h1>
                        <p class="mt-5 m-0 text-[14.5px] leading-[1.65] text-text-muted max-w-[44ch]">
                            Clear answers about process, pricing, collaboration, ownership, and support.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 mt-[18px] faq2-support">
                        <div class="grid grid-cols-[36px_minmax(0,1fr)] items-start gap-[12px_14px] p-[18px] border rounded-2xl faq2-support-card faq2-support-card--blue">
                            <span class="w-9 h-9 rounded-[10px] inline-grid place-items-center faq2-support-icon">
                                <i class="ph ph-question text-[18px]"></i>
                            </span>
                            <span class="font-display text-[17px] font-bold tracking-[-0.02em] text-text-strong">Straight answers</span>
                            <p class="[grid-column:2] text-[13px] leading-[1.6] text-text-muted m-0">The most common questions we hear before a project starts, all in one place.</p>
                            <span class="[grid-column:2] inline-flex items-center gap-1.5 mt-1 font-mono text-[11.5px] font-semibold tracking-[0.04em] faq2-support-cta">
                                {{ str_pad((string) count($faqs), 2, '0', STR_PAD_LEFT) }} answered questions
                            </span>
                        </div>
                    </div>
                </aside>

                <div class="flex flex-col" role="list" data-faq-list>
                    @forelse ($faqs as $faq)
                        <div class="faq2-item {{ $loop->first ? 'is-open ' : '' }}relative border-t border-border-soft {{ $loop->last ? 'border-b' : '' }}" role="listitem">
                            <button
                                type="button"
                                class="w-full grid grid-cols-[44px_minmax(0,1fr)_36px] items-center gap-4 py-[22px] px-1 bg-transparent border-none cursor-pointer text-left font-[inherit] faq2-item-trigger"
                                data-faq-trigger
                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                            >
                                <span class="font-mono text-[11.5px] font-semibold tracking-[0.14em] text-text-light tabular-nums faq2-item-num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="font-display text-[clamp(16px,1.6vw,19px)] font-bold tracking-[-0.015em] leading-[1.35] text-text-muted text-balance faq2-item-q">{{ $faq->question }}</span>
                                <span class="relative w-8 h-8 rounded-pill bg-white border border-border-default inline-grid place-items-center text-text-muted overflow-hidden faq2-item-toggle">
                                    <span class="absolute bg-current rounded-sm top-1/2 left-1/2 w-3 h-0.5 faq2-item-toggle-bar faq2-item-toggle-bar--h"></span>
                                    <span class="absolute bg-current rounded-sm top-1/2 left-1/2 w-0.5 h-3 faq2-item-toggle-bar faq2-item-toggle-bar--v"></span>
                                </span>
                            </button>
                            <div class="faq2-item-collapse">
                                <div class="overflow-hidden min-h-0 faq2-item-collapse-inner">
                                    <p class="m-0 mb-[22px] pl-[60px] text-[14.5px] leading-[1.7] text-text-muted max-w-[64ch] faq2-item-a">
                                        {{ $faq->answer }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="py-12 text-[14.5px] leading-[1.7] text-text-muted">No FAQs are available at this time.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
