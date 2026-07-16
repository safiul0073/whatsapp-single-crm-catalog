@php
    $faqs = $faqs ?? collect();

    $d = $section->data ?? [];
    $headingLineOne        = $d['heading_line_one']        ?? __('Common questions,');
    $headingLineTwo        = $d['heading_line_two']        ?? __('clear answers.');
    $subheading            = $d['subheading']              ?? __('The questions founders ask in the first 30-minute scope call.');
    $callCardTitle         = $d['call_card_title']         ?? __('Talk to a senior engineer');
    $callCardBody          = $d['call_card_body']          ?? __('30-minute scope call with someone who would actually build it. Usually within 48 hours.');
    $callCardCtaText       = $d['call_card_cta_text']      ?? __('Book a call');
    $callCardCtaLink       = $d['call_card_cta_link']      ?? '#book-call';
    $callCardEngineersText = $d['call_card_engineers_text'] ?? __('8 senior engineers · all on call');
    $emailCardTitle        = $d['email_card_title']        ?? __('Drop us a brief');
    $emailCardBody         = $d['email_card_body']         ?? __("Send a one-pager and we'll come back with a written response within two working days.");
    $emailCardEmail        = $d['email_card_email']        ?? 'hello.com';
@endphp

<section class="relative overflow-hidden isolate py-[clamp(72px,9vw,120px)] bg-white" aria-labelledby="faq2-heading">
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden [mask-image:linear-gradient(180deg,#000_0%,#000_78%,transparent_100%)]"
        aria-hidden="true">
        <div class="absolute top-0 bottom-0 left-1/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute top-0 bottom-0 left-1/2 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute top-0 bottom-0 left-3/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[160px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
        <div class="absolute left-0 right-0 top-[320px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
        <div class="absolute left-0 right-0 top-[480px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
        <div class="absolute left-0 right-0 top-[640px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
        <div class="absolute left-0 right-0 top-[800px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
    </div>
    <div class="section-container relative z-1">
        <div
            class="grid grid-cols-[minmax(min(300px,100%),420px)_minmax(0,1fr)] gap-[clamp(40px,5vw,80px)] items-start faq2-layout">
            <aside class="relative">
                <div
                    class="sticky top-24 max-h-[calc(100vh-120px)] overflow-y-auto scrollbar-none flex flex-col gap-[18px] faq2-side-sticky">
                    <span
                        class="inline-flex items-center gap-2 self-start py-1.5 px-3 pl-2.5 bg-white/70 border border-border-soft rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue"><span
                            class="w-1.5 h-1.5 rounded-pill bg-brand-blue shadow-faq-eyebrow-dot"></span>FAQ ·
                        {{ str_pad((string) $faqs->count(), 2, '0', STR_PAD_LEFT) }}</span>
                    <h2 id="faq2-heading"
                        class="flex flex-col font-display text-[clamp(34px,4vw,48px)] font-extrabold tracking-[-0.025em] leading-[1.06] text-brand-navy-ink m-0 text-balance">
                        <span>{{ $headingLineOne }}</span><span class="faq2-h2-accent">{{ $headingLineTwo }}</span>
                    </h2>
                    <p class="m-0 text-[14.5px] leading-[1.65] text-text-muted max-w-[44ch]">{{ $subheading }}</p>

                    <div class="flex flex-col gap-3 mt-[18px] faq2-support">
                        <a href="{{ $callCardCtaLink }}"
                            class="grid grid-cols-[36px_minmax(0,1fr)] items-start gap-[12px_14px] p-[18px] border rounded-2xl no-underline faq2-support-card faq2-support-card--blue">
                            <span class="w-9 h-9 rounded-[10px] inline-grid place-items-center faq2-support-icon"><i
                                    data-lucide="phone" class="w-4 h-4"></i></span>
                            <span class="flex flex-col gap-1">
                                <span class="font-display text-body-sm font-bold tracking-body text-brand-navy-ink">{{ $callCardTitle }}</span>
                                <span class="text-[12.5px] leading-5 text-text-muted">{{ $callCardBody }}</span>
                            </span>
                            <span class="[grid-column:2] flex flex-col gap-2 mt-3.5 pt-3.5 faq2-support-avatars"
                                aria-hidden="true">
                                <span class="inline-flex pl-2">
                                    <span
                                        class="w-[30px] h-[30px] rounded-pill inline-grid place-items-center overflow-hidden bg-brand-blue text-white border-2 border-white shadow-faq-avatar -ml-2 relative font-mono text-[10px] font-bold tracking-[0.02em] faq2-support-avatar">SA</span>
                                    <span
                                        class="w-[30px] h-[30px] rounded-pill inline-grid place-items-center overflow-hidden bg-brand-green text-white border-2 border-white shadow-faq-avatar -ml-2 relative font-mono text-[10px] font-bold tracking-[0.02em] faq2-support-avatar">MK</span>
                                    <span
                                        class="w-[30px] h-[30px] rounded-pill inline-grid place-items-center overflow-hidden bg-brand-navy-ink text-white border-2 border-white shadow-faq-avatar -ml-2 relative font-mono text-[10px] font-bold tracking-[0.02em] faq2-support-avatar">RN</span>
                                    <span
                                        class="w-[30px] h-[30px] rounded-pill inline-grid place-items-center overflow-hidden border-2 -ml-2 relative font-mono text-[10px] font-bold tracking-[0.02em] text-brand-blue faq2-support-avatar is-overflow">+5</span>
                                </span>
                                <span
                                    class="inline-flex items-center gap-[7px] font-mono text-[10.5px] font-semibold tracking-[0.08em] text-text-muted"><span
                                        class="relative w-1.5 h-1.5 rounded-pill bg-brand-green flex-none shadow-faq-pulse faq2-support-avatars-pulse"></span>{{ $callCardEngineersText }}</span>
                            </span>
                            <span
                                class="[grid-column:2] inline-flex items-center gap-1.5 mt-1 font-mono text-[11.5px] font-semibold tracking-[0.04em] faq2-support-cta">{{ $callCardCtaText }} <i data-lucide="arrow-right" class="w-[13px] h-[13px]"></i></span>
                        </a>

                        <a href="mailto:{{ $emailCardEmail }}"
                            class="grid grid-cols-[36px_minmax(0,1fr)] items-start gap-[12px_14px] p-[18px] border rounded-2xl no-underline faq2-support-card faq2-support-card--green">
                            <span class="w-9 h-9 rounded-[10px] inline-grid place-items-center faq2-support-icon"><i
                                    data-lucide="mail" class="w-4 h-4"></i></span>
                            <span class="flex flex-col gap-1">
                                <span class="font-display text-body-sm font-bold tracking-body text-brand-navy-ink">{{ $emailCardTitle }}</span>
                                <span class="text-[12.5px] leading-5 text-text-muted">{{ $emailCardBody }}</span>
                            </span>
                            <span
                                class="[grid-column:2] inline-flex items-center gap-1.5 mt-1 font-mono text-[11.5px] font-semibold tracking-[0.04em] faq2-support-cta">{{ $emailCardEmail }}
                                <i data-lucide="arrow-right" class="w-[13px] h-[13px]"></i></span>
                        </a>
                    </div>
                </div>
            </aside>

            <div class="flex flex-col" role="list" data-faq-list>
                @foreach ($faqs as $faq)
                    <div class="faq2-item {{ $loop->first ? 'is-open ' : '' }}relative border-t border-border-soft {{ $loop->last ? 'border-b' : '' }}"
                        role="listitem">
                        <button type="button"
                            class="w-full grid grid-cols-[44px_minmax(0,1fr)_36px] items-center gap-4 py-[22px] px-1 bg-transparent border-none cursor-pointer text-left font-[inherit] faq2-item-trigger"
                            data-faq-trigger aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                            <span
                                class="font-mono text-[11.5px] font-semibold tracking-[0.14em] text-text-light tabular-nums faq2-item-num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span
                                class="font-display text-[clamp(16px,1.6vw,19px)] font-bold tracking-[-0.015em] leading-[1.35] text-text-muted text-balance faq2-item-q">{{ $faq->question }}</span>
                            <span
                                class="relative w-8 h-8 rounded-pill bg-white border border-border-default inline-grid place-items-center text-text-muted overflow-hidden faq2-item-toggle"><span
                                    class="absolute bg-current rounded-sm top-1/2 left-1/2 w-3 h-0.5 faq2-item-toggle-bar faq2-item-toggle-bar--h"></span><span
                                    class="absolute bg-current rounded-sm top-1/2 left-1/2 w-0.5 h-3 faq2-item-toggle-bar faq2-item-toggle-bar--v"></span></span>
                        </button>
                        <div class="faq2-item-collapse">
                            <div class="overflow-hidden min-h-0 faq2-item-collapse-inner">
                                <p
                                    class="m-0 mb-[22px] pl-[60px] text-[14.5px] leading-[1.7] text-text-muted max-w-[64ch] faq2-item-a">
                                    {{ $faq->answer }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
