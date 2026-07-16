  <div class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-8" data-video-modal role="dialog"
      aria-modal="true" aria-label="{{ __('Showreel video') }}">
      <div class="absolute inset-0 bg-brand-navy-ink/85 backdrop-blur-sm" data-video-backdrop></div>
      <div class="relative w-full max-w-4xl aspect-video bg-black rounded-2xl overflow-hidden shadow-2xl">
          <button type="button" data-video-close aria-label="{{ __('Close video') }}"
              class="absolute top-3 right-3 z-10 inline-grid place-items-center w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white border border-white/15 backdrop-blur-md transition-colors cursor-pointer">
              <i data-lucide="x" class="w-4 h-4" aria-hidden="true"></i>
          </button>
          <iframe data-video-iframe title="{{ __('Classic showreel') }}"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
              allowfullscreen class="w-full h-full"></iframe>
      </div>
  </div>
