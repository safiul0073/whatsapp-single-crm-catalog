@php
    $d = $section->data ?? [];

    $eyebrowText = $d['eyebrow_text'] ?? __('How we work');
    $headingOne = $d['heading_line_one'] ?? __('Five phases.');
    $headingTwo = $d['heading_line_two'] ?? __('One team. One timeline.');
    $subheading = $d['subheading'] ?? __('From discovery to year-two roadmap — the same engineers and designers from kickoff to scale, with a clear deliverable at every step.');
    $phases = $d['phases'] ?? [
        [
            'number' => '01',
            'duration' => '1 week',
            'icon' => 'compass',
            'phase_label' => 'Discovery',
            'title' => 'Understand the why before the what.',
            'description' => 'Stakeholder interviews, technical audit, and a written scope doc. We leave with a one-page brief and a milestone plan you can sign off on.',
            'deliverables' => "Discovery brief\nTechnical audit\nMilestone plan\nRisk register",
        ],
        [
            'number' => '02',
            'duration' => '2 weeks',
            'icon' => 'palette',
            'phase_label' => 'Design',
            'title' => 'Hi-fi designs ready to build, not pitch.',
            'description' => 'Research-backed flows, design tokens, and motion specs in Figma. Every screen reviewed against the engineering plan so it survives the build.',
            'deliverables' => "Hi-fi mockups\nDesign system tokens\nInteraction specs\nAccessibility audit",
        ],
        [
            'number' => '03',
            'duration' => '4–6 weeks',
            'icon' => 'code-2',
            'phase_label' => 'Build',
            'title' => 'Working software every week, not every quarter.',
            'description' => 'Two-week sprints with a public preview environment. Telemetry from sprint one. Demo every Friday with a clear changelog.',
            'deliverables' => "Preview environment\nWeekly demos\nTelemetry pipeline\nContinuous deploys",
        ],
        [
            'number' => '04',
            'duration' => '1 week',
            'icon' => 'rocket',
            'phase_label' => 'Launch',
            'title' => 'Production-ready, observable, and yours.',
            'description' => 'Deploy to your cloud, your domain, your repo. Runbook, on-call rotation, and a post-launch report on day three.',
            'deliverables' => "Production deploy\nRunbook + alerts\nDay-3 launch report\nCode handover",
        ],
        [
            'number' => '05',
            'duration' => 'Ongoing',
            'icon' => 'trending-up',
            'phase_label' => 'Scale',
            'title' => 'Same team, year-two roadmap.',
            'description' => 'Quarterly roadmap reviews, retainer or pay-as-you-need engineering, and direct-line access to the team that shipped v1.',
            'deliverables' => "Quarterly review\nRetainer engineering\nRoadmap planning\nOn-call support",
        ],
    ];
    $phaseCount = count($phases);
    $phaseCountPadded = str_pad($phaseCount, 2, '0', STR_PAD_LEFT);
@endphp

<section id="proc2-section" class="relative py-18 md:py-24 lg:pb-28 xl:pb-40 bg-white overflow-hidden isolate"
    aria-labelledby="proc2-heading">
    <!-- Dashed grid lines -->
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
        <div class="absolute left-0 right-0 top-[960px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
    </div>
    <div class="section-container relative z-1">
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] gap-10 lg:gap-16 xl:gap-20 items-start">
            <!-- Left: heading + controls -->
            <div class="flex flex-col gap-8 lg:sticky lg:top-24">
                <div>
                    <span
                        class="inline-flex items-center gap-2 py-1.5 pl-2.5 pr-3 bg-white/70 border border-tint-navy rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue"><span
                            class="w-1.5 h-1.5 rounded-pill bg-brand-blue shadow-proc-eyebrow-dot"></span>{{ $eyebrowText }}</span>
                    <h2 id="proc2-heading"
                        class="flex flex-col font-display text-[clamp(32px,4vw,52px)] font-extrabold tracking-heading leading-[1.06] text-brand-navy-ink mt-[18px] text-balance">
                        <span>{{ $headingOne }}</span><span
                            class="bg-grad-mark bg-clip-text text-transparent">{{ $headingTwo }}</span>
                    </h2>
                    <p class="mt-3.5 text-[15px] leading-[1.65] text-text-muted max-w-[48ch]">
                        {{ $subheading }}
                    </p>
                </div>

                <!-- Prev / Next controls -->
                <div
                    class="inline-flex items-center gap-3.5 py-1.5 px-2 bg-white border border-border-default rounded-pill shadow-proc-controls self-start">
                    <button type="button"
                        class="proc2-arrow inline-grid place-items-center w-9 h-9 rounded-pill bg-white border border-border-default text-brand-navy-ink cursor-pointer"
                        data-proc-prev aria-label="{{ __('Previous phase') }}">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    </button>
                    <span class="font-mono text-[13px] font-semibold tracking-[0.04em] tabular-nums"><span
                            class="text-brand-navy-ink" data-proc-counter>01</span><span class="text-text-light"> /
                            {{ $phaseCountPadded }}</span></span>
                    <button type="button"
                        class="proc2-arrow inline-grid place-items-center w-9 h-9 rounded-pill bg-white border border-border-default text-brand-navy-ink cursor-pointer"
                        data-proc-next aria-label="{{ __('Next phase') }}">
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <!-- Right: stacked cards -->
            <div class="proc2-stack relative h-[clamp(400px,52vw,600px)]" style="perspective: 1400px" data-proc-stack
                role="tablist" aria-label="{{ __('Development process') }}">
                @foreach ($phases as $index => $phase)
                    @php
                        $deliverables = array_filter(array_map('trim', explode("\n", $phase['deliverables'] ?? '')));
                    @endphp
                    <article
                        class="proc2-card absolute inset-0 mx-auto max-w-full h-full bg-white border border-tint-navy rounded-3xl p-7 md:p-9 lg:p-11 shadow-proc-card overflow-hidden"
                        data-proc-card="{{ $index }}">
                        <span
                            class="proc2-card-bignum absolute right-5 md:right-8 lg:right-11 -bottom-2 font-display text-[clamp(160px,22vw,280px)] font-extrabold tracking-[-0.06em] leading-[0.85] text-brand-blue/5 pointer-events-none select-none">{{ $phase['number'] ?? str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="relative z-1 flex items-center gap-3.5 flex-wrap mb-5 md:mb-7 lg:mb-8">
                            <span class="font-mono text-micro font-bold tracking-[0.18em] uppercase text-brand-blue">Phase
                                {{ $phase['number'] ?? str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span><span
                                class="w-[22px] h-px bg-border-strong"></span><span
                                class="font-mono text-micro font-semibold tracking-[0.12em] uppercase text-text-muted">{{ $phase['duration'] ?? '' }}</span>
                        </div>
                        <div
                            class="proc2-card-body relative z-1 grid grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] gap-7 md:gap-12 lg:gap-14 h-[calc(100%-56px)] items-start">
                            <div class="flex flex-col gap-2.5 min-w-0">
                                <div
                                    class="w-11 h-11 rounded-md bg-brand-blue/8 border border-brand-blue/18 text-brand-blue inline-grid place-items-center mb-1.5">
                                    <i class="ph {{ $phase['icon'] ?? 'ph-circle' }} text-xl"></i>
                                </div>
                                <div class="font-display font-bold text-brand-blue uppercase tracking-[0.14em] text-sm">
                                    {{ $phase['phase_label'] ?? '' }}
                                </div>
                                <h3
                                    class="font-display text-[clamp(24px,3vw,36px)] font-extrabold tracking-heading leading-[1.1] text-brand-navy-ink text-balance">
                                    {{ $phase['title'] ?? '' }}
                                </h3>
                                <p class="text-[15px] leading-[1.65] text-text-muted mt-1 max-w-[56ch]">
                                    {{ $phase['description'] ?? '' }}
                                </p>
                            </div>
                            <div
                                class="proc2-card-right flex flex-col gap-3.5 pl-0 md:pl-5 lg:pl-7 border-l-0 md:border-l border-tint-navy">
                                <span
                                    class="font-mono text-micro font-semibold tracking-[0.14em] uppercase text-text-light">{{ __('Deliverables') }}</span>
                                <ul class="list-none p-0 m-0 flex flex-col gap-2.5">
                                    @foreach ($deliverables as $deliverable)
                                        <li class="flex items-center gap-2.5 text-sm text-text-strong">
                                            <span
                                                class="flex-none w-5 h-5 rounded-pill bg-brand-green/12 text-brand-green inline-grid place-items-center"><i
                                                    data-lucide="check" class="w-[11px] h-[11px]"
                                                    style="stroke-width: 2.6"></i></span><span>{{ $deliverable }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
        <!-- end grid -->
    </div>
</section>
