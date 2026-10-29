<x-layouts.user :title="__('Storefront Settings & Studio')">

@php
    $defaults = [
        'name' => 'Store',
        'primary' => '#0866FF',
        'hover' => '#0055D4',
        'footer_color' => '#0A2540',
        'logo' => '',
        'favicon' => '',
        'announcement' => '',
        'contact_email' => '',
        'contact_phone' => '',
        'footer_text' => '',
        'password_login' => false,
        'app_download_enabled' => true,
        'wholesale_enabled' => true,
        'app_download_title' => 'Experience On Mobile',
        'app_download_subtitle' => 'Download our mobile apps for faster checkout and exclusive drops.',
        'app_ios_url' => '',
        'app_android_url' => '',
    ];
    $cfg = array_merge($defaults, (array) ($settings ?? []));

    // Normalize initial logo URL for preview if stored as relative storage path
    $initialLogo = $cfg['logo'] ?? '';
    $initialLogoUrl = '';
    if (!empty($initialLogo)) {
        if (str_starts_with($initialLogo, 'http://') || str_starts_with($initialLogo, 'https://') || str_starts_with($initialLogo, '//')) {
            $initialLogoUrl = $initialLogo;
        } else {
            $initialLogoUrl = asset('storage/' . ltrim($initialLogo, '/'));
        }
    }

    // Normalize initial favicon URL for preview if stored as relative storage path
    $initialFavicon = $cfg['favicon'] ?? '';
    $initialFaviconUrl = '';
    if (!empty($initialFavicon)) {
        if (str_starts_with($initialFavicon, 'http://') || str_starts_with($initialFavicon, 'https://') || str_starts_with($initialFavicon, '//')) {
            $initialFaviconUrl = $initialFavicon;
        } else {
            $initialFaviconUrl = asset('storage/' . ltrim($initialFavicon, '/'));
        }
    }

    $creds = (array) ($credentials ?? []);
    $marketingApiUrl = $creds['marketing_api_url'] ?? rtrim((string) config('app.url', 'http://127.0.0.1:8000'), '/');
    $mediaOrigin = $creds['media_origin'] ?? $marketingApiUrl;
    $platformSecret = $creds['platform_secret'] ?? '';
    $sessionSecret = $creds['session_secret'] ?? '';
    $revalidationSecret = $creds['revalidation_secret'] ?? '';
    $previewSecret = $creds['preview_secret'] ?? '';
    $storefrontAppUrl = $creds['storefront_url'] ?? config('storefront.url', 'http://localhost:3000');

    $systemDomain = $domains->firstWhere('type', 'system');
    $customDomains = $domains->where('type', 'custom');
    $primaryDomain = $domains->firstWhere('is_primary', true);
    $rootDomain = ($creds['root_domain'] ?? '') ?: config('storefront.root_domain', 'localhost');
    $dnsTarget = ($creds['dns_target'] ?? '') ?: config('storefront.dns_target', '');
    $storeUrl = $primaryDomain
        ? ((request()->isSecure() ? 'https://' : 'http://') . $primaryDomain->host)
        : ($storefrontAppUrl ?: null);

    $storefrontEnvContent = implode("\n", [
        'MARKETING_API_URL=' . $marketingApiUrl,
        'STOREFRONT_MEDIA_ORIGIN=' . $mediaOrigin,
        'STOREFRONT_PLATFORM_SECRET=' . $platformSecret,
        'STOREFRONT_SESSION_SECRET=' . $sessionSecret,
        'STOREFRONT_REVALIDATION_SECRET=' . $revalidationSecret,
        'STOREFRONT_PREVIEW_SECRET=' . $previewSecret,
    ]);

    $backendEnvContent = implode("\n", [
        'STOREFRONT_URL=' . $storefrontAppUrl,
        'STOREFRONT_ROOT_DOMAIN=' . $rootDomain,
        'STOREFRONT_DNS_TARGET=' . $dnsTarget,
        'STOREFRONT_PLATFORM_SECRET=' . $platformSecret,
        'STOREFRONT_REVALIDATION_SECRET=' . $revalidationSecret,
        'STOREFRONT_PREVIEW_SECRET=' . $previewSecret,
    ]);

    $envVariablesList = [
        [
            'key' => 'MARKETING_API_URL',
            'value' => $marketingApiUrl,
            'secret' => false,
            'rotate_target' => null,
            'description' => __('Base URL of this WaPro backend application that the Next.js storefront calls for catalog, cart, and checkout APIs.'),
        ],
        [
            'key' => 'STOREFRONT_MEDIA_ORIGIN',
            'value' => $mediaOrigin,
            'secret' => false,
            'rotate_target' => null,
            'description' => __('Origin URL used by Next.js image optimization and /storage media proxy.'),
        ],
        [
            'key' => 'STOREFRONT_PLATFORM_SECRET',
            'value' => $platformSecret,
            'secret' => true,
            'rotate_target' => 'platform_secret',
            'description' => __('Shared 64-character secret sent in X-Storefront-Secret header to authenticate internal storefront API requests.'),
        ],
        [
            'key' => 'STOREFRONT_SESSION_SECRET',
            'value' => $sessionSecret,
            'secret' => true,
            'rotate_target' => 'session_secret',
            'description' => __('32+ byte AES-256-GCM key used by the Next.js storefront to seal and unseal encrypted customer session cookies (sf_session).'),
        ],
        [
            'key' => 'STOREFRONT_REVALIDATION_SECRET',
            'value' => $revalidationSecret,
            'secret' => true,
            'rotate_target' => 'revalidation_secret',
            'description' => __('HMAC-SHA256 signing key used to trigger instant Next.js cache revalidation (/api/revalidate) when you update theme, pages, or products.'),
        ],
        [
            'key' => 'STOREFRONT_PREVIEW_SECRET',
            'value' => $previewSecret,
            'secret' => true,
            'rotate_target' => 'preview_secret',
            'description' => __('HMAC-SHA256 signing key used to authorize draft page previews (/api/draft).'),
        ],
    ];

    $menuSlots = [
        'header' => ['label' => 'Header Menu', 'icon' => 'ph-browsers'],
        'footer' => ['label' => 'Footer Menu', 'icon' => 'ph-columns'],
        'mobile' => ['label' => 'Mobile Drawer', 'icon' => 'ph-device-mobile'],
    ];

    $pendingReviewsCount = $reviews->count();
@endphp

<div
    class="mx-auto w-full max-w-7xl space-y-6 pb-12"
    x-data="{
        activeTab: (() => {
            const serverTab = {{ Illuminate\Support\Js::from(session('storefront_tab') ?? request('tab')) }};
            if (serverTab) return serverTab;
            if (window.location.hash && window.location.hash.length > 1) {
                return window.location.hash.substring(1);
            }
            try {
                const stored = localStorage.getItem('storefront_active_tab');
                if (stored) return stored;
            } catch (e) {}
            return 'settings';
        })(),
        copiedKey: null,
        showSecrets: false,
        switchTab(tab) {
            this.activeTab = tab;
            try {
                localStorage.setItem('storefront_active_tab', tab);
            } catch (e) {}
            if (history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tab);
                url.hash = tab;
                history.replaceState(null, null, url.toString());
            } else {
                window.location.hash = tab;
            }
        },
        init() {
            Object.keys(this.sliderData).forEach((id) => {
                this.sliderData[id].slides.forEach((slide) => this.ensureSlideUid(slide));
                this.openSlide[id] = this.sliderData[id].slides[0]?._uid ?? null;
            });
            if (this.activeTab && history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', this.activeTab);
                url.hash = this.activeTab;
                history.replaceState(null, null, url.toString());
            }
            window.addEventListener('popstate', () => {
                const h = window.location.hash && window.location.hash.length > 1 ? window.location.hash.substring(1) : '';
                const q = new URLSearchParams(window.location.search).get('tab');
                if (h || q) {
                    this.activeTab = h || q;
                }
            });
        },
        copyText(text, key) {
            if (!text) return;
            navigator.clipboard.writeText(text).then(() => {
                this.copiedKey = key;
                setTimeout(() => { if (this.copiedKey === key) this.copiedKey = null; }, 2000);
            });
        },
        downloadEnv(content, filename = '.env') {
            const blob = new Blob([content + '\n'], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },

        // Settings form state & live interactive preview
        form: {
            name: {{ Illuminate\Support\Js::from($cfg['name'] ?? 'Store') }},
            primary: {{ Illuminate\Support\Js::from($cfg['primary'] ?? '#0866FF') }},
            hover: {{ Illuminate\Support\Js::from($cfg['hover'] ?? '#0055D4') }},
            footer_color: {{ Illuminate\Support\Js::from($cfg['footer_color'] ?? '#0A2540') }},
            logo: {{ Illuminate\Support\Js::from($cfg['logo'] ?? '') }},
            favicon: {{ Illuminate\Support\Js::from($cfg['favicon'] ?? '') }},
            logo_height: {{ Illuminate\Support\Js::from((int) ($cfg['logo_height'] ?? 68)) }},
            logo_show_name: {{ Illuminate\Support\Js::from((bool) ($cfg['logo_show_name'] ?? false)) }},
            announcement: {{ Illuminate\Support\Js::from($cfg['announcement'] ?? '') }},
            contact_email: {{ Illuminate\Support\Js::from($cfg['contact_email'] ?? '') }},
            contact_phone: {{ Illuminate\Support\Js::from($cfg['contact_phone'] ?? '') }},
            footer_text: {{ Illuminate\Support\Js::from($cfg['footer_text'] ?? '') }},
            password_login: {{ Illuminate\Support\Js::from((bool) ($cfg['password_login'] ?? false)) }},
            app_download_enabled: {{ Illuminate\Support\Js::from((bool) ($cfg['app_download_enabled'] ?? true)) }},
            wholesale_enabled: {{ Illuminate\Support\Js::from((bool) ($cfg['wholesale_enabled'] ?? true)) }},
            app_download_title: {{ Illuminate\Support\Js::from($cfg['app_download_title'] ?? 'Experience On Mobile') }},
            app_download_subtitle: {{ Illuminate\Support\Js::from($cfg['app_download_subtitle'] ?? 'Download our mobile apps for faster checkout and exclusive drops.') }},
            app_ios_url: {{ Illuminate\Support\Js::from($cfg['app_ios_url'] ?? '') }},
            app_android_url: {{ Illuminate\Support\Js::from($cfg['app_android_url'] ?? '') }}
        },
        initialLogoUrl: {{ Illuminate\Support\Js::from($initialLogoUrl) }},
        initialFaviconUrl: {{ Illuminate\Support\Js::from($initialFaviconUrl) }},
        previewDevice: 'desktop',
        btnHover: false,
        uploadingApp: false,
        uploadAppMessage: '',
        uploadAppPlatform: '',

        uploadAppFile(event, platform) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;

            this.uploadingApp = true;
            this.uploadAppPlatform = platform;
            this.uploadAppMessage = 'Uploading ' + file.name + '...';

            const formData = new FormData();
            formData.append('file', file);
            formData.append('platform', platform);
            formData.append('_token', '{{ csrf_token() }}');

            fetch('{{ route('user.commerce.storefront.upload-app') }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('Upload failed with status ' + res.status);
                return res.json();
            })
            .then(data => {
                if (data.url) {
                    if (platform === 'ios') {
                        this.form.app_ios_url = data.url;
                    } else {
                        this.form.app_android_url = data.url;
                    }
                    const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
                    this.uploadAppMessage = 'Uploaded ' + file.name + ' (' + sizeMb + ' MB) successfully!';
                }
            })
            .catch(err => {
                this.uploadAppMessage = 'Upload error: ' + (err.message || 'Failed to upload');
            })
            .finally(() => {
                this.uploadingApp = false;
                setTimeout(() => { this.uploadAppMessage = ''; }, 6000);
            });
        },

        getPreviewLogo() {
            if (!this.form.logo) return '';
            if (this.form.logo.startsWith('http://') || this.form.logo.startsWith('https://') || this.form.logo.startsWith('//')) {
                return this.form.logo;
            }
            if (this.form.logo === {{ Illuminate\Support\Js::from($cfg['logo'] ?? '') }} && this.initialLogoUrl) {
                return this.initialLogoUrl;
            }
            return '/storage/' + this.form.logo.replace(/^\/+/, '');
        },

        getPreviewFavicon() {
            if (!this.form.favicon) return '';
            if (this.form.favicon.startsWith('http://') || this.form.favicon.startsWith('https://') || this.form.favicon.startsWith('//')) {
                return this.form.favicon;
            }
            if (this.form.favicon === {{ Illuminate\Support\Js::from($cfg['favicon'] ?? '') }} && this.initialFaviconUrl) {
                return this.initialFaviconUrl;
            }
            return '/storage/' + this.form.favicon.replace(/^\/+/, '');
        },

        setPalette(primary, hover, footer) {
            this.form.primary = primary;
            this.form.hover = hover;
            this.form.footer_color = footer;
        },

        openGalleryForLogo() {
            if (typeof window.openMediaLibrary === 'function') {
                window.openMediaLibrary((media) => {
                    if (media && media.url) {
                        this.form.logo = media.url;
                    }
                }, { type: 'image' });
            }
        },

        openGalleryForFavicon() {
            if (typeof window.openMediaLibrary === 'function') {
                window.openMediaLibrary((media) => {
                    if (media && media.url) {
                        this.form.favicon = media.url;
                    }
                }, { type: 'image' });
            }
        },

        openGalleryForSlide(sliderId, slideIndex, field = 'image', mediaType = 'image') {
            if (typeof window.openMediaLibrary === 'function') {
                window.openMediaLibrary((media) => {
                    const slide = this.sliderData[sliderId]?.slides?.[slideIndex];
                    if (!media || !media.url || !slide) {
                        return;
                    }
                    const isVideo = media.type === 'video' || /\.(mp4|webm|mov|m4v)(\?|$)/i.test(media.url);
                    if (isVideo) {
                        // A video picked from an image button belongs in the video field, never the image/poster.
                        slide.video = media.url;
                        if (field !== 'video' && /\.(mp4|webm|mov|m4v)(\?|$)/i.test(slide[field] || '')) {
                            slide[field] = '';
                        }
                        return;
                    }
                    slide[field === 'video' ? 'image' : field] = media.url;
                }, { type: mediaType });
            }
        },

        // Menu Editor State
        activeMenuSlot: (new URLSearchParams(window.location.search).get('slot') || 'header'),
        defaultMenus: {{ Illuminate\Support\Js::from($defaultMenus ?? ['header' => [], 'footer' => [], 'mobile' => []]) }},
        menuItems: {
            header: {{ Illuminate\Support\Js::from(collect($menus->firstWhere('slot', 'header') ? ($menus->firstWhere('slot', 'header')->items ?? []) : ($defaultMenus['header'] ?? []))->map(fn ($item) => array_merge(['label' => '', 'href' => '/', 'mega' => 'auto', 'badge' => '', 'children' => []], (array) $item))->values()->all()) }},
            footer: {{ Illuminate\Support\Js::from(collect($menus->firstWhere('slot', 'footer') ? ($menus->firstWhere('slot', 'footer')->items ?? []) : ($defaultMenus['footer'] ?? []))->map(fn ($item) => array_merge(['label' => '', 'href' => '/', 'mega' => 'none', 'badge' => '', 'children' => []], (array) $item))->values()->all()) }},
            mobile: {{ Illuminate\Support\Js::from(collect($menus->firstWhere('slot', 'mobile') ? ($menus->firstWhere('slot', 'mobile')->items ?? []) : ($defaultMenus['mobile'] ?? []))->map(fn ($item) => array_merge(['label' => '', 'href' => '/', 'mega' => 'auto', 'badge' => '', 'children' => []], (array) $item))->values()->all()) }}
        },
        menuRawMode: { header: false, footer: false, mobile: false },

        addMenuItem(slot, label = 'New Link', href = '/', mega = 'auto', badge = '') {
            this.menuItems[slot].push({
                label: label,
                href: href,
                mega: slot === 'footer' ? 'none' : mega,
                badge: badge,
                children: []
            });
        },
        removeMenuItem(slot, index) {
            this.menuItems[slot].splice(index, 1);
        },
        moveMenuItem(slot, from, to) {
            if (to < 0 || to >= this.menuItems[slot].length) return;
            const item = this.menuItems[slot].splice(from, 1)[0];
            this.menuItems[slot].splice(to, 0, item);
        },
        addSubMenuItem(slot, parentIndex, label = 'Sub-category', href = '/products') {
            if (!Array.isArray(this.menuItems[slot][parentIndex].children)) {
                this.menuItems[slot][parentIndex].children = [];
            }
            this.menuItems[slot][parentIndex].mega = 'custom';
            this.menuItems[slot][parentIndex].children.push({ label: label, href: href });
        },
        removeSubMenuItem(slot, parentIndex, childIndex) {
            if (Array.isArray(this.menuItems[slot][parentIndex]?.children)) {
                this.menuItems[slot][parentIndex].children.splice(childIndex, 1);
            }
        },
        resetDefaultMenu(slot) {
            const defaults = this.defaultMenus[slot] || [];
            this.menuItems[slot] = JSON.parse(JSON.stringify(defaults));
        },

        // Sliders Editor State
        activeSliderId: {{ Illuminate\Support\Js::from((string) (session('storefront_slider') ?? request('slider') ?? $sliders->first()?->id ?? 'new')) }},
        sliderData: {
            @foreach($sliders as $slider)
                '{{ $slider->id }}': {
                    name: {{ Illuminate\Support\Js::from($slider->name) }},
                    slides: {{ Illuminate\Support\Js::from(collect($slider->slides ?? [])->map(fn ($slide) => array_merge(['title' => '', 'subtitle' => '', 'image' => '', 'mobile_image' => '', 'video' => '', 'button_text' => '', 'button_link' => '', 'active' => true], (array) $slide))->values()->all()) }},
                    raw: false
                },
            @endforeach
            'new': {
                name: '',
                slides: [],
                raw: false
            }
        },
        openSlide: {},
        confirmDeleteSlide: null,
        slideUidSeq: 0,
        ensureSlideUid(slide) {
            if (!slide._uid) {
                slide._uid = 'slide-' + (++this.slideUidSeq);
            }
            return slide._uid;
        },
        serializeSlides(sliderId) {
            return JSON.stringify(this.sliderData[sliderId].slides.map(({ _uid, ...slide }) => slide));
        },
        isSlideOpen(sliderId, slide) {
            return this.openSlide[sliderId] === slide._uid;
        },
        toggleSlide(sliderId, slide) {
            this.openSlide[sliderId] = this.isSlideOpen(sliderId, slide) ? null : slide._uid;
        },
        focusSlide(slide) {
            this.$nextTick(() => {
                const card = [...document.querySelectorAll('[data-slide-uid]')].find((el) => el.dataset.slideUid === slide._uid);
                if (!card) {
                    return;
                }
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                card.querySelector('[data-slide-title]')?.focus({ preventScroll: true });
            });
        },
        addSlide(sliderId) {
            const slide = {
                title: 'New Headline Slide',
                subtitle: 'Add an engaging promotion or story description here.',
                image: '',
                mobile_image: '',
                video: '',
                button_text: 'Shop Now',
                button_link: '/products',
                active: true
            };
            this.ensureSlideUid(slide);
            this.sliderData[sliderId].slides.unshift(slide);
            this.openSlide[sliderId] = slide._uid;
            this.focusSlide(slide);
        },
        duplicateSlide(sliderId, index) {
            const { _uid, ...source } = this.sliderData[sliderId].slides[index];
            const copy = { ...source, title: (source.title || '') + ' (Copy)' };
            this.ensureSlideUid(copy);
            this.sliderData[sliderId].slides.splice(index + 1, 0, copy);
            this.openSlide[sliderId] = copy._uid;
            this.focusSlide(copy);
        },
        removeSlide(sliderId, index) {
            this.sliderData[sliderId].slides.splice(index, 1);
            this.confirmDeleteSlide = null;
        },
        moveSlide(sliderId, from, to) {
            if (to < 0 || to >= this.sliderData[sliderId].slides.length) return;
            const item = this.sliderData[sliderId].slides.splice(from, 1)[0];
            this.sliderData[sliderId].slides.splice(to, 0, item);
        }
    }"
>
    {{-- Page Header --}}
    <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20">
                <i class="ph ph-storefront text-2xl"></i>
            </span>
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="heading-3 text-title text-xl font-bold sm:text-2xl">{{ __('Storefront Studio') }}</h1>
                    @if($primaryDomain)
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            {{ __('Live') }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-body sm:text-sm">{{ __('Branding, domains, menus, content and marketing for your store.') }}</p>
                @if($primaryDomain)
                    <div x-data="{ copied: false }" class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 bg-white px-2 py-1 font-mono text-[11px] text-neutral-600 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300">
                        <i class="ph ph-globe-simple text-sm text-neutral-400"></i>
                        <span class="truncate">{{ $primaryDomain->host }}</span>
                        <button type="button" @click="navigator.clipboard.writeText('{{ $storeUrl }}'); copied = true; setTimeout(() => copied = false, 1500)" class="text-neutral-400 hover:text-primary" title="{{ __('Copy store URL') }}">
                            <i class="ph text-sm" :class="copied ? 'ph-check text-primary' : 'ph-copy'"></i>
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            @if($storeUrl)
                <a href="{{ $storeUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm rounded-lg flex items-center gap-1.5 text-xs font-semibold">
                    <i class="ph ph-arrow-square-out text-base"></i>
                    <span>{{ __('Visit Storefront') }}</span>
                </a>
            @endif
            <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative">
                <button type="button" @click="open = !open" :aria-expanded="open" class="btn btn-outline btn-sm rounded-lg flex items-center gap-1.5 text-xs font-semibold hover:border-primary hover:text-primary transition-colors">
                    <i class="ph ph-dots-three-circle text-base"></i>
                    <span>{{ __('More') }}</span>
                    <i class="ph ph-caret-down text-xs transition-transform" :class="open && 'rotate-180'"></i>
                </button>
                <div x-show="open" x-transition.origin.top.right x-cloak class="absolute right-0 z-30 mt-2 w-56 overflow-hidden rounded-xl border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-800 dark:bg-neutral-900">
                    <a href="{{ route('user.commerce.pack-templates.index') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-neutral-700 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">
                        <i class="ph ph-package text-base text-neutral-400"></i>{{ __('Custom Pack Templates') }}
                    </a>
                    <a href="{{ route('user.commerce.products.index') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-neutral-700 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">
                        <i class="ph ph-tag text-base text-neutral-400"></i>{{ __('Manage Catalog') }}
                    </a>
                    <div class="my-1 border-t border-neutral-100 dark:border-neutral-800"></div>
                    <button type="button" @click="open = false; switchTab('deployment')" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm text-neutral-700 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">
                        <i class="ph ph-key text-base text-neutral-400"></i>{{ __('API & .env Setup') }}
                    </button>
                </div>
            </div>
        </div>
    </header>

    {{-- Session Notifications --}}
    @if(session('success'))
        <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/90 p-4 text-sm text-emerald-900 shadow-xs dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300" role="status">
            <div class="flex items-center gap-3">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-500 text-white shrink-0">
                    <i class="ph ph-check text-lg"></i>
                </span>
                <div>
                    <p class="font-medium">{{ session('success') }}</p>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">{{ __('Changes take effect immediately on your storefront.') }}</p>
                </div>
            </div>
            <button type="button" @click="$el.closest('[role=status]').remove()" class="text-emerald-700 hover:text-emerald-900 dark:text-emerald-400">
                <i class="ph ph-x text-base"></i>
            </button>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50/90 p-4 text-sm text-red-900 shadow-xs dark:border-red-800 dark:bg-red-950/40 dark:text-red-300" role="alert">
            <div class="flex items-start gap-3">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-red-500 text-white shrink-0 mt-0.5">
                    <i class="ph ph-warning-circle text-lg"></i>
                </span>
                <div class="space-y-1">
                    <p class="font-semibold">{{ __('Please review the following issues:') }}</p>
                    <ul class="list-disc space-y-0.5 pl-5 text-xs text-red-800 dark:text-red-300">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Main Tab Navigation Strip --}}
    {{-- Main Tab Navigation --}}
    @php
        $studioTabGroups = [
            [
                ['settings', 'ph-palette', __('Theme'), null, false],
                ['domains', 'ph-globe', __('Domains'), $customDomains->count() ?: null, false],
                ['menus', 'ph-list-dashes', __('Menus'), null, false],
            ],
            [
                ['sliders', 'ph-slideshow', __('Sliders'), $sliders->count() ?: null, false],
                ['live', 'ph-broadcast', __('Live & Videos'), isset($liveStreams) && $liveStreams->count() ? $liveStreams->count() : null, false],
                ['pages', 'ph-browsers', __('Pages'), $pages->count() ?: null, false],
                ['content', 'ph-article', __('Blog & FAQs'), (($blogs->count() ?? 0) + ($faqs->count() ?? 0)) ?: null, false],
            ],
            [
                ['coupons', 'ph-ticket', __('Coupons'), $coupons->count() ?: null, false],
                ['reviews', 'ph-star', __('Reviews'), $pendingReviewsCount ?: null, true],
                ['inquiries', 'ph-chats-circle', __('Inquiries'), ($inquiries->count() ?? 0) ?: null, false],
            ],
            [
                ['chat-channels', 'ph-chat-circle-dots', __('Chat & Contact'), null, false],
                ['tracking', 'ph-chart-line-up', __('Ads & Tracking'), null, false],
                ['deployment', 'ph-key', __('API & Deploy'), null, false],
            ],
        ];
    @endphp
    <nav class="flex flex-wrap items-center gap-1 rounded-xl border border-neutral-200/80 bg-white p-1.5 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
        @foreach($studioTabGroups as $group)
            @if(! $loop->first)
                <span class="mx-0.5 hidden h-5 w-px bg-neutral-200 sm:block dark:bg-neutral-700"></span>
            @endif
            @foreach($group as [$tabKey, $tabIcon, $tabLabel, $tabCount, $tabAlert])
                <button
                    type="button"
                    @click="switchTab('{{ $tabKey }}')"
                    :class="activeTab === '{{ $tabKey }}'
                        ? 'bg-primary text-white shadow-xs'
                        : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white'"
                    class="flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-[13px] font-medium transition-colors"
                >
                    <i class="ph {{ $tabIcon }} text-base"></i>
                    <span>{{ $tabLabel }}</span>
                    @if($tabCount)
                        <span
                            :class="activeTab === '{{ $tabKey }}' ? 'bg-white/20 text-white' : '{{ $tabAlert ? 'bg-amber-500 text-white' : 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300' }}'"
                            class="rounded-full px-1.5 py-px text-[10px] font-semibold"
                        >{{ $tabCount }}</span>
                    @endif
                </button>
            @endforeach
        @endforeach
    </nav>

    {{-- =====================================================================
         TAB 1: STOREFRONT THEME & IDENTITY (SMOOTH SETTINGS UPDATE)
         ===================================================================== --}}
    <div x-show="activeTab === 'settings'" x-cloak class="grid grid-cols-1 gap-8 lg:grid-cols-12 items-start">
        {{-- Settings Form (Left 7 cols) --}}
        <div class="space-y-6 lg:col-span-7">
            <form method="POST" action="{{ route('user.commerce.storefront.settings') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')
                <input type="hidden" name="_tab" value="settings">

                {{-- 1. Brand Identity Card --}}
                <div class="rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-5">
                    <div class="flex items-center gap-2.5 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                            <i class="ph ph-storefront text-lg"></i>
                        </span>
                        <div>
                            <h2 class="heading-4 text-title text-base font-semibold">{{ __('Store Identity & Announcement') }}</h2>
                            <p class="text-xs text-body">{{ __('Configure your store name, logo, and top header announcement bar.') }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="form-label flex items-center gap-1.5" for="store_name">
                                <i class="ph ph-storefront text-primary text-base"></i>
                                <span>{{ __('Store Name') }}</span> <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="store_name"
                                type="text"
                                name="config[name]"
                                x-model="form.name"
                                required
                                maxlength="150"
                                class="form-input"
                                placeholder="e.g. Apex Streetwear"
                            >
                            <p class="form-hint">{{ __('Visible in the browser title, storefront header, invoices, and emails.') }}</p>
                        </div>

                        <div>
                            <label class="form-label flex items-center gap-1.5" for="store_announcement">
                                <i class="ph ph-megaphone-simple text-primary text-base"></i>
                                <span>{{ __('Top Announcement Banner') }}</span>
                            </label>
                            <input
                                id="store_announcement"
                                type="text"
                                name="config[announcement]"
                                x-model="form.announcement"
                                maxlength="250"
                                class="form-input"
                                placeholder="e.g. Free shipping on all orders over $50! ✨"
                            >
                            <p class="form-hint">{{ __('Displayed prominently at the very top of your storefront. Leave empty to hide.') }}</p>
                        </div>

                        <div>
                            <label class="form-label flex items-center gap-1.5" for="store_logo">
                                <i class="ph ph-image text-primary text-base"></i>
                                <span>{{ __('Store Logo') }}</span>
                            </label>

                            {{-- Modern Logo Gallery Picker Card --}}
                            <div class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-4">
                                <div class="flex flex-col sm:flex-row items-center gap-4">
                                    {{-- Visual Logo Preview Container --}}
                                    <div class="relative h-20 w-36 shrink-0 overflow-hidden rounded-xl border border-neutral-200 bg-white p-2 shadow-2xs dark:border-neutral-700 dark:bg-neutral-900 flex items-center justify-center">
                                        <template x-if="getPreviewLogo()">
                                            <img
                                                :src="getPreviewLogo()"
                                                alt="Store Logo"
                                                class="max-h-full max-w-full object-contain"
                                                x-on:error="$el.style.display='none'"
                                            >
                                        </template>
                                        <template x-if="!getPreviewLogo()">
                                            <div class="flex flex-col items-center justify-center text-neutral-400 gap-1">
                                                <i class="ph ph-image text-2xl"></i>
                                                <span class="text-[10px] uppercase font-bold tracking-wider text-neutral-400">{{ __('No Logo') }}</span>
                                            </div>
                                        </template>
                                    </div>

                                    {{-- Action Buttons & Guidance --}}
                                    <div class="flex-1 space-y-2 text-center sm:text-left">
                                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                            <button
                                                type="button"
                                                @click="openGalleryForLogo()"
                                                class="btn btn-primary btn-sm rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-2xs"
                                            >
                                                <i class="ph ph-images text-base"></i>
                                                <span>{{ __('Choose from Image Gallery') }}</span>
                                            </button>

                                            <button
                                                type="button"
                                                x-show="form.logo"
                                                @click="form.logo = ''"
                                                class="btn btn-outline btn-sm rounded-lg text-xs text-red-600 hover:bg-red-50 hover:border-red-300 dark:hover:bg-red-950/40 flex items-center gap-1"
                                            >
                                                <i class="ph ph-trash text-sm"></i>
                                                <span>{{ __('Remove Logo') }}</span>
                                            </button>
                                        </div>
                                        <p class="text-xs text-neutral-500">
                                            {{ __('Upload or select your brand logo from the Media Gallery. Transparent PNG, SVG, or WebP recommended.') }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Direct URL / Storage Path Input --}}
                                <div class="pt-3 border-t border-neutral-200/60 dark:border-neutral-700/60 space-y-1">
                                    <div class="flex items-center justify-between">
                                        <label for="store_logo" class="text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 uppercase tracking-wider">
                                            {{ __('Logo URL or Storage Path:') }}
                                        </label>
                                        <template x-if="form.logo">
                                            <span class="text-[10px] text-neutral-400 font-mono truncate max-w-xs" x-text="form.logo"></span>
                                        </template>
                                    </div>
                                    <input
                                        id="store_logo"
                                        type="text"
                                        name="config[logo]"
                                        x-model="form.logo"
                                        class="form-input text-xs font-mono"
                                        placeholder="https://example.com/logo.png or settings/logo.png"
                                    >
                                </div>

                                {{-- Logo Display Size (Height) & Brand Text Controls --}}
                                <div class="pt-3 border-t border-neutral-200/60 dark:border-neutral-700/60 space-y-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <label for="store_logo_height" class="text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 uppercase tracking-wider">
                                            {{ __('Header Logo Display Height:') }}
                                            <span class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 font-mono text-primary font-bold" x-text="form.logo_height + 'px'"></span>
                                        </label>
                                        <div class="flex items-center gap-1">
                                            <button type="button" @click="form.logo_height = 48" :class="Number(form.logo_height) === 48 ? 'bg-primary text-white border-primary' : 'bg-white text-neutral-600 border-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-700'" class="rounded-md border px-2 py-0.5 text-[10px] font-semibold transition">
                                                {{ __('Compact (48px)') }}
                                            </button>
                                            <button type="button" @click="form.logo_height = 68" :class="Number(form.logo_height) === 68 ? 'bg-primary text-white border-primary' : 'bg-white text-neutral-600 border-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-700'" class="rounded-md border px-2 py-0.5 text-[10px] font-semibold transition">
                                                {{ __('Large (68px)') }}
                                            </button>
                                            <button type="button" @click="form.logo_height = 88" :class="Number(form.logo_height) === 88 ? 'bg-primary text-white border-primary' : 'bg-white text-neutral-600 border-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-700'" class="rounded-md border px-2 py-0.5 text-[10px] font-semibold transition">
                                                {{ __('XL (88px)') }}
                                            </button>
                                        </div>
                                    </div>
                                    <input
                                        id="store_logo_height"
                                        type="range"
                                        min="36"
                                        max="108"
                                        step="2"
                                        name="config[logo_height]"
                                        x-model.number="form.logo_height"
                                        class="w-full accent-primary cursor-pointer"
                                    >
                                    <div class="flex items-center justify-between gap-3 pt-1">
                                        <div>
                                            <label for="store_logo_show_name" class="text-xs font-semibold text-neutral-800 dark:text-neutral-200 cursor-pointer">
                                                {{ __('Also show Store Name text next to Logo') }}
                                            </label>
                                            <p class="text-[11px] text-neutral-500">
                                                {{ __('Helpful if your uploaded logo is an icon/emblem without text, or if you want bold brand text beside it.') }}
                                            </p>
                                        </div>
                                        <div class="shrink-0">
                                            <input type="hidden" name="config[logo_show_name]" value="0">
                                            <label class="relative inline-flex cursor-pointer items-center">
                                                <input
                                                    id="store_logo_show_name"
                                                    type="checkbox"
                                                    name="config[logo_show_name]"
                                                    value="1"
                                                    x-model="form.logo_show_name"
                                                    class="peer sr-only"
                                                >
                                                <div class="peer h-5 w-9 rounded-full bg-neutral-300 transition-colors after:absolute after:top-[2px] after:left-[2px] after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full dark:bg-neutral-700"></div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Store Favicon (Browser Tab Icon) --}}
                        <div class="pt-2 border-t border-neutral-100 dark:border-neutral-800">
                            <label class="form-label flex items-center gap-1.5" for="store_favicon">
                                <i class="ph ph-browsers text-primary text-base"></i>
                                <span>{{ __('Browser Favicon (Tab Icon)') }}</span>
                            </label>

                            <div class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-4">
                                <div class="flex flex-col sm:flex-row items-center gap-4">
                                    {{-- Visual Favicon Preview in Simulated Browser Tab --}}
                                    <div class="relative shrink-0 flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2 shadow-2xs dark:border-neutral-700 dark:bg-neutral-900">
                                        <div class="h-6 w-6 rounded flex items-center justify-center overflow-hidden bg-neutral-100 dark:bg-neutral-800">
                                            <template x-if="getPreviewFavicon()">
                                                <img
                                                    :src="getPreviewFavicon()"
                                                    alt="Favicon"
                                                    class="h-5 w-5 object-contain"
                                                    x-on:error="$el.style.display='none'"
                                                >
                                            </template>
                                            <template x-if="!getPreviewFavicon() && getPreviewLogo()">
                                                <img
                                                    :src="getPreviewLogo()"
                                                    alt="Favicon (from Logo)"
                                                    class="h-5 w-5 object-contain"
                                                    x-on:error="$el.style.display='none'"
                                                >
                                            </template>
                                            <template x-if="!getPreviewFavicon() && !getPreviewLogo()">
                                                <i class="ph ph-globe text-neutral-400 text-sm"></i>
                                            </template>
                                        </div>
                                        <div class="text-xs font-semibold text-neutral-800 dark:text-neutral-200 truncate max-w-[100px]" x-text="form.name || 'Store'"></div>
                                    </div>

                                    {{-- Action Buttons & Guidance --}}
                                    <div class="flex-1 space-y-2 text-center sm:text-left">
                                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                            <button
                                                type="button"
                                                @click="openGalleryForFavicon()"
                                                class="btn btn-primary btn-sm rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-2xs"
                                            >
                                                <i class="ph ph-images text-base"></i>
                                                <span>{{ __('Choose Favicon from Gallery') }}</span>
                                            </button>

                                            <button
                                                type="button"
                                                x-show="form.favicon"
                                                @click="form.favicon = ''"
                                                class="btn btn-outline btn-sm rounded-lg text-xs text-red-600 hover:bg-red-50 hover:border-red-300 dark:hover:bg-red-950/40 flex items-center gap-1"
                                            >
                                                <i class="ph ph-trash text-sm"></i>
                                                <span>{{ __('Remove Favicon') }}</span>
                                            </button>
                                        </div>
                                        <p class="text-xs text-neutral-500">
                                            {{ __('Appears in the browser tab next to the page title. Recommended: 32×32px, 64×64px, or SVG. If left empty, your store logo or default icon is used.') }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Direct URL / Storage Path Input --}}
                                <div class="pt-3 border-t border-neutral-200/60 dark:border-neutral-700/60 space-y-1">
                                    <div class="flex items-center justify-between">
                                        <label for="store_favicon" class="text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 uppercase tracking-wider">
                                            {{ __('Favicon URL or Storage Path:') }}
                                        </label>
                                        <template x-if="form.favicon">
                                            <span class="text-[10px] text-neutral-400 font-mono truncate max-w-xs" x-text="form.favicon"></span>
                                        </template>
                                    </div>
                                    <input
                                        id="store_favicon"
                                        type="text"
                                        name="config[favicon]"
                                        x-model="form.favicon"
                                        class="form-input text-xs font-mono"
                                        placeholder="https://... or /storage/media/favicon.png"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. Visual Color Theme & Quick Palettes Card --}}
                <div class="rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-5">
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-4 dark:border-neutral-800">
                        <div class="flex items-center gap-2.5">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                                <i class="ph ph-palette text-lg"></i>
                            </span>
                            <div>
                                <h2 class="heading-4 text-title text-base font-semibold">{{ __('Color Theme & Styling') }}</h2>
                                <p class="text-xs text-body">{{ __('Preview changes appear instantly on the right. Save to apply them to the public storefront.') }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- One-Click Curated Color Palettes --}}
                    <div>
                        <span class="text-xs font-semibold text-neutral-600 dark:text-neutral-400 uppercase tracking-wider block mb-2.5">
                            {{ __('Curated Theme Presets (1-Click Apply):') }}
                        </span>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {{-- WhatsApp Green --}}
                            <button
                                type="button"
                                @click="setPalette('#1faa53', '#178a43', '#075e54')"
                                class="flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50/70 p-2 text-left hover:border-emerald-500 hover:bg-emerald-50/40 transition-all dark:border-neutral-800 dark:bg-neutral-800/40 dark:hover:border-emerald-500"
                            >
                                <span class="flex -space-x-1 shrink-0">
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#1faa53]"></span>
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#178a43]"></span>
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#075e54]"></span>
                                </span>
                                <span class="text-xs font-medium text-neutral-800 dark:text-neutral-200 truncate">{{ __('Emerald') }}</span>
                            </button>

                            {{-- Ocean Electric --}}
                            <button
                                type="button"
                                @click="setPalette('#0866FF', '#0055D4', '#0A2540')"
                                class="flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50/70 p-2 text-left hover:border-blue-500 hover:bg-blue-50/40 transition-all dark:border-neutral-800 dark:bg-neutral-800/40 dark:hover:border-blue-500"
                            >
                                <span class="flex -space-x-1 shrink-0">
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#0866FF]"></span>
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#0055D4]"></span>
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#0A2540]"></span>
                                </span>
                                <span class="text-xs font-medium text-neutral-800 dark:text-neutral-200 truncate">{{ __('Ocean Blue') }}</span>
                            </button>

                            {{-- Midnight Slate --}}
                            <button
                                type="button"
                                @click="setPalette('#0f172a', '#1e293b', '#020617')"
                                class="flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50/70 p-2 text-left hover:border-slate-500 hover:bg-slate-50/40 transition-all dark:border-neutral-800 dark:bg-neutral-800/40 dark:hover:border-slate-500"
                            >
                                <span class="flex -space-x-1 shrink-0">
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#0f172a]"></span>
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#1e293b]"></span>
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#020617]"></span>
                                </span>
                                <span class="text-xs font-medium text-neutral-800 dark:text-neutral-200 truncate">{{ __('Midnight') }}</span>
                            </button>

                            {{-- Royal Violet --}}
                            <button
                                type="button"
                                @click="setPalette('#7c3aed', '#6d28d9', '#1e1b4b')"
                                class="flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50/70 p-2 text-left hover:border-purple-500 hover:bg-purple-50/40 transition-all dark:border-neutral-800 dark:bg-neutral-800/40 dark:hover:border-purple-500"
                            >
                                <span class="flex -space-x-1 shrink-0">
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#7c3aed]"></span>
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#6d28d9]"></span>
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#1e1b4b]"></span>
                                </span>
                                <span class="text-xs font-medium text-neutral-800 dark:text-neutral-200 truncate">{{ __('Royal Violet') }}</span>
                            </button>

                            {{-- Crimson Luxury --}}
                            <button
                                type="button"
                                @click="setPalette('#e11d48', '#be123c', '#4c0519')"
                                class="flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50/70 p-2 text-left hover:border-rose-500 hover:bg-rose-50/40 transition-all dark:border-neutral-800 dark:bg-neutral-800/40 dark:hover:border-rose-500"
                            >
                                <span class="flex -space-x-1 shrink-0">
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#e11d48]"></span>
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#be123c]"></span>
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#4c0519]"></span>
                                </span>
                                <span class="text-xs font-medium text-neutral-800 dark:text-neutral-200 truncate">{{ __('Crimson') }}</span>
                            </button>

                            {{-- Warm Amber --}}
                            <button
                                type="button"
                                @click="setPalette('#d97706', '#b45309', '#451a03')"
                                class="flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50/70 p-2 text-left hover:border-amber-500 hover:bg-amber-50/40 transition-all dark:border-neutral-800 dark:bg-neutral-800/40 dark:hover:border-amber-500"
                            >
                                <span class="flex -space-x-1 shrink-0">
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#d97706]"></span>
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#b45309]"></span>
                                    <span class="h-4 w-4 rounded-full border border-white bg-[#451a03]"></span>
                                </span>
                                <span class="text-xs font-medium text-neutral-800 dark:text-neutral-200 truncate">{{ __('Warm Amber') }}</span>
                            </button>
                        </div>
                    </div>

                    {{-- 3 Individual Color Pickers --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 pt-2">
                        {{-- Primary Color --}}
                        <div class="rounded-xl border border-neutral-200 bg-neutral-50/50 p-3.5 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold text-neutral-800 dark:text-neutral-200" for="color_primary">
                                    {{ __('Primary Color') }} <span class="text-red-500">*</span>
                                </label>
                                <span class="h-4 w-4 rounded-full border border-neutral-300 shadow-2xs" :style="'background-color: ' + form.primary"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    type="color"
                                    x-model="form.primary"
                                    class="h-9 w-9 shrink-0 cursor-pointer rounded-lg border border-neutral-200 bg-white p-0.5 dark:border-neutral-700 dark:bg-neutral-900"
                                    title="{{ __('Pick primary color') }}"
                                >
                                <input
                                    id="color_primary"
                                    type="text"
                                    name="config[primary]"
                                    x-model="form.primary"
                                    required
                                    maxlength="7"
                                    pattern="^#[0-9a-fA-F]{6}$"
                                    class="form-input form-input-sm font-mono text-xs uppercase"
                                    placeholder="#0866FF"
                                >
                            </div>
                            <p class="text-[11px] text-neutral-500">{{ __('Buttons, badges & active links') }}</p>
                        </div>

                        {{-- Hover Color --}}
                        <div class="rounded-xl border border-neutral-200 bg-neutral-50/50 p-3.5 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold text-neutral-800 dark:text-neutral-200" for="color_hover">
                                    {{ __('Hover State') }} <span class="text-red-500">*</span>
                                </label>
                                <span class="h-4 w-4 rounded-full border border-neutral-300 shadow-2xs" :style="'background-color: ' + form.hover"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    type="color"
                                    x-model="form.hover"
                                    class="h-9 w-9 shrink-0 cursor-pointer rounded-lg border border-neutral-200 bg-white p-0.5 dark:border-neutral-700 dark:bg-neutral-900"
                                    title="{{ __('Pick hover color') }}"
                                >
                                <input
                                    id="color_hover"
                                    type="text"
                                    name="config[hover]"
                                    x-model="form.hover"
                                    required
                                    maxlength="7"
                                    pattern="^#[0-9a-fA-F]{6}$"
                                    class="form-input form-input-sm font-mono text-xs uppercase"
                                    placeholder="#0055D4"
                                >
                            </div>
                            <p class="text-[11px] text-neutral-500">{{ __('Button mouse-over & focus glow') }}</p>
                        </div>

                        {{-- Footer Color --}}
                        <div class="rounded-xl border border-neutral-200 bg-neutral-50/50 p-3.5 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold text-neutral-800 dark:text-neutral-200" for="color_footer">
                                    {{ __('Footer Color') }} <span class="text-red-500">*</span>
                                </label>
                                <span class="h-4 w-4 rounded-full border border-neutral-300 shadow-2xs" :style="'background-color: ' + form.footer_color"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    type="color"
                                    x-model="form.footer_color"
                                    class="h-9 w-9 shrink-0 cursor-pointer rounded-lg border border-neutral-200 bg-white p-0.5 dark:border-neutral-700 dark:bg-neutral-900"
                                    title="{{ __('Pick footer background color') }}"
                                >
                                <input
                                    id="color_footer"
                                    type="text"
                                    name="config[footer_color]"
                                    x-model="form.footer_color"
                                    required
                                    maxlength="7"
                                    pattern="^#[0-9a-fA-F]{6}$"
                                    class="form-input form-input-sm font-mono text-xs uppercase"
                                    placeholder="#0A2540"
                                >
                            </div>
                            <p class="text-[11px] text-neutral-500">{{ __('Footer background surface') }}</p>
                        </div>
                    </div>
                </div>

                {{-- 3. Contact & Support Information Card --}}
                <div class="rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-5">
                    <div class="flex items-center gap-2.5 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                            <i class="ph ph-headset text-lg"></i>
                        </span>
                        <div>
                            <h2 class="heading-4 text-title text-base font-semibold">{{ __('Contact & Customer Support') }}</h2>
                            <p class="text-xs text-body">{{ __('Public contact points displayed on storefront header, footer, and receipts.') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="form-label flex items-center gap-1.5" for="contact_email">
                                <i class="ph ph-envelope-simple text-primary text-base"></i>
                                <span>{{ __('Customer Support Email') }}</span>
                            </label>
                            <input
                                id="contact_email"
                                type="email"
                                name="config[contact_email]"
                                x-model="form.contact_email"
                                class="form-input"
                                placeholder="support@yourbrand.com"
                            >
                        </div>

                        <div>
                            <label class="form-label flex items-center gap-1.5" for="contact_phone">
                                <i class="ph ph-phone text-primary text-base"></i>
                                <span>{{ __('Support Phone / WhatsApp') }}</span>
                            </label>
                            <input
                                id="contact_phone"
                                type="text"
                                name="config[contact_phone]"
                                x-model="form.contact_phone"
                                class="form-input"
                                placeholder="+1 (800) 555-0199"
                            >
                        </div>
                    </div>
                </div>

                {{-- 4. Footer & Policies Card --}}
                <div class="rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-5">
                    <div class="flex items-center gap-2.5 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                            <i class="ph ph-article text-lg"></i>
                        </span>
                        <div>
                            <h2 class="heading-4 text-title text-base font-semibold">{{ __('Footer Text & Policies') }}</h2>
                            <p class="text-xs text-body">{{ __('Storefront summary, company slogan, or copyright notice.') }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="footer_text">
                            {{ __('Footer Copyright & Description') }}
                        </label>
                        <textarea
                            id="footer_text"
                            name="config[footer_text]"
                            x-model="form.footer_text"
                            rows="2"
                            maxlength="500"
                            class="form-input"
                            placeholder="Direct from factory wholesale and retail streetwear, premium heavyweight hoodies, tees, and denim."
                        ></textarea>
                    </div>
                </div>

                {{-- 5. Mobile App Downloads (iOS & Android) Card --}}
                <div class="rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2.5">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                                <i class="ph ph-package text-lg"></i>
                            </span>
                            <div>
                                <h2 class="heading-4 text-title text-base font-semibold">{{ __('Wholesale Selling') }}</h2>
                                <p class="text-xs text-body">{{ __('When off, the store sells retail only. Wholesale prices, packs, pack templates and wholesale menus are hidden on the dashboard, storefront and mobile app.') }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="config[wholesale_enabled]" value="0">
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input
                                    type="checkbox"
                                    name="config[wholesale_enabled]"
                                    value="1"
                                    x-model="form.wholesale_enabled"
                                    class="peer sr-only"
                                >
                                <div class="peer h-6 w-11 rounded-full bg-neutral-300 transition-colors after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full dark:bg-neutral-700"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-5">
                    <div class="flex items-center justify-between border-b border-neutral-100 pb-4 dark:border-neutral-800">
                        <div class="flex items-center gap-2.5">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                                <i class="ph ph-device-mobile text-lg"></i>
                            </span>
                            <div>
                                <h2 class="heading-4 text-title text-base font-semibold">{{ __('Mobile App Downloads (iOS & Android)') }}</h2>
                                <p class="text-xs text-body">{{ __('Upload your mobile app (.apk) or provide App Store and Google Play links for storefront footer download.') }}</p>
                            </div>
                        </div>
                        {{-- Toggle Switch --}}
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="config[app_download_enabled]" value="0">
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input
                                    type="checkbox"
                                    name="config[app_download_enabled]"
                                    value="1"
                                    x-model="form.app_download_enabled"
                                    class="peer sr-only"
                                >
                                <div class="peer h-6 w-11 rounded-full bg-neutral-300 transition-colors after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full dark:bg-neutral-700"></div>
                            </label>
                        </div>
                    </div>

                    <div x-show="form.app_download_enabled" class="space-y-5">
                        {{-- App Section Heading --}}
                        <div>
                            <label class="form-label text-xs font-semibold" for="app_download_title">
                                {{ __('Footer App Section Heading') }}
                            </label>
                            <input
                                id="app_download_title"
                                type="text"
                                name="config[app_download_title]"
                                x-model="form.app_download_title"
                                maxlength="120"
                                class="form-input text-xs"
                                placeholder="Experience On Mobile or Download Our App"
                            >
                            <p class="form-hint">{{ __('Small label displayed above the two app badges in the footer.') }}</p>
                        </div>

                        {{-- iOS and Android 2-Column Grid --}}
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            {{-- iOS / App Store Card --}}
                            <div class="rounded-xl border border-neutral-200 bg-neutral-50/60 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 text-sm">
                                            <i class="ph ph-apple-logo"></i>
                                        </span>
                                        <span class="font-bold text-xs text-neutral-900 dark:text-white">{{ __('iOS App (Apple)') }}</span>
                                    </div>
                                    <span class="rounded bg-neutral-200/70 px-2 py-0.5 text-[10px] font-semibold text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
                                        {{ __('App Store') }}
                                    </span>
                                </div>

                                <div>
                                    <label class="form-label text-xs" for="app_ios_url">{{ __('App Store URL / iOS Link') }}</label>
                                    <input
                                        id="app_ios_url"
                                        type="text"
                                        name="config[app_ios_url]"
                                        x-model="form.app_ios_url"
                                        class="form-input text-xs font-mono"
                                        placeholder="https://apps.apple.com/app/your-app-id"
                                    >
                                    <p class="form-hint">{{ __('Leave empty to use standard Apple App Store landing page.') }}</p>
                                </div>
                            </div>

                            {{-- Android / Google Play / Direct APK Upload Card --}}
                            <div class="rounded-xl border border-neutral-200 bg-neutral-50/60 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-emerald-600 text-white text-sm">
                                            <i class="ph ph-google-play-logo"></i>
                                        </span>
                                        <span class="font-bold text-xs text-neutral-900 dark:text-white">{{ __('Android App (Google Play / APK)') }}</span>
                                    </div>
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400">
                                        {{ __('Direct APK / Store') }}
                                    </span>
                                </div>

                                <div>
                                    <label class="form-label text-xs" for="app_android_url">{{ __('Google Play URL or APK Download Link') }}</label>
                                    <input
                                        id="app_android_url"
                                        type="text"
                                        name="config[app_android_url]"
                                        x-model="form.app_android_url"
                                        class="form-input text-xs font-mono"
                                        placeholder="https://play.google.com/store/apps/details?id=... or .apk URL"
                                    >
                                </div>

                                {{-- Direct APK File Upload Helper --}}
                                <div class="pt-2 border-t border-neutral-200/60 dark:border-neutral-700/60 space-y-2">
                                    <span class="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 block">
                                        {{ __('Direct APK Upload:') }}
                                    </span>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <label class="btn btn-outline btn-sm rounded-lg text-xs font-semibold cursor-pointer flex items-center gap-1.5 hover:border-primary hover:text-primary transition-colors">
                                            <i class="ph ph-upload-simple text-sm"></i>
                                            <span>{{ __('Choose .apk file') }}</span>
                                            <input
                                                type="file"
                                                name="android_app_file"
                                                accept=".apk,.zip"
                                                class="sr-only"
                                                @change="uploadAppFile($event, 'android')"
                                            >
                                        </label>
                                        <template x-if="uploadingApp && uploadAppPlatform === 'android'">
                                            <span class="inline-flex items-center gap-1.5 text-xs text-primary animate-pulse font-medium">
                                                <i class="ph ph-spinner gap-1 animate-spin"></i>
                                                <span x-text="uploadAppMessage"></span>
                                            </span>
                                        </template>
                                        <template x-if="!uploadingApp && uploadAppMessage && uploadAppPlatform === 'android'">
                                            <span class="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                                                <i class="ph ph-check-circle"></i>
                                                <span x-text="uploadAppMessage"></span>
                                            </span>
                                        </template>
                                    </div>
                                    <p class="text-[11px] text-neutral-500">
                                        {{ __('Upload your compiled Android build (.apk). It is stored on your server and linked automatically.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 5. Customer Authentication Security Card --}}
                <div class="rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                            <i class="ph ph-shield-check text-lg"></i>
                        </span>
                        <div>
                            <h2 class="heading-4 text-title text-base font-semibold">{{ __('Customer Account Authentication') }}</h2>
                            <p class="text-xs text-body">{{ __('Choose how customers authenticate and manage orders on your storefront.') }}</p>
                        </div>
                    </div>

                    <div class="flex items-start justify-between gap-4 rounded-xl border border-neutral-200 bg-neutral-50/60 p-4 dark:border-neutral-800 dark:bg-neutral-800/40">
                        <div class="space-y-1">
                            <label for="password_login" class="text-sm font-semibold text-neutral-800 dark:text-neutral-200 cursor-pointer">
                                {{ __('Enable Customer Password Login') }}
                            </label>
                            <p class="text-xs text-neutral-500">
                                {{ __('When enabled, customers can set and use traditional passwords. When disabled, customers sign in securely via seamless OTP / Magic Code.') }}
                            </p>
                        </div>
                        <div class="shrink-0 pt-0.5">
                            <input type="hidden" name="config[password_login]" value="0">
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input
                                    id="password_login"
                                    type="checkbox"
                                    name="config[password_login]"
                                    value="1"
                                    x-model="form.password_login"
                                    class="peer sr-only"
                                >
                                <div class="peer h-6 w-11 rounded-full bg-neutral-300 transition-colors after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full dark:bg-neutral-700"></div>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Submit Button Bar --}}
                <div class="flex items-center justify-between rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="text-xs text-neutral-500 flex items-center gap-2">
                        <i class="ph ph-info text-base text-primary"></i>
                        <span>{{ __('The public storefront applies these saved colors on its next refresh.') }}</span>
                    </div>
                    <button
                        type="submit"
                        class="btn btn-primary rounded-xl px-6 py-2.5 text-sm font-semibold flex items-center gap-2 shadow-sm transition-all"
                    >
                        <i class="ph ph-check-circle text-lg"></i>
                        <span>{{ __('Save & Apply Theme') }}</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Live Storefront Interactive Preview (Right 5 cols) --}}
        <div class="space-y-3 lg:col-span-5 lg:sticky lg:top-6">
            {{-- Preview Controls Header --}}
            <div class="flex items-center justify-between px-1">
                <div class="flex items-center gap-2">
                    <i class="ph ph-device-mobile-camera text-base text-primary"></i>
                    <span class="text-xs font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider">{{ __('Live Interactive Preview — Unsaved') }}</span>
                </div>
                <div class="flex items-center rounded-lg border border-neutral-200 bg-white p-0.5 dark:border-neutral-800 dark:bg-neutral-900">
                    <button
                        type="button"
                        @click="previewDevice = 'desktop'"
                        :class="previewDevice === 'desktop' ? 'bg-primary text-white shadow-2xs' : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'"
                        class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors flex items-center gap-1"
                    >
                        <i class="ph ph-desktop"></i>
                        <span>{{ __('Desktop') }}</span>
                    </button>
                    <button
                        type="button"
                        @click="previewDevice = 'mobile'"
                        :class="previewDevice === 'mobile' ? 'bg-primary text-white shadow-2xs' : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'"
                        class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors flex items-center gap-1"
                    >
                        <i class="ph ph-device-mobile"></i>
                        <span>{{ __('Mobile') }}</span>
                    </button>
                </div>
            </div>

            {{-- Mockup Browser Container --}}
            <div
                class="overflow-hidden rounded-2xl border border-neutral-300/80 bg-neutral-900 shadow-xl transition-all duration-300 dark:border-neutral-700 mx-auto"
                :class="previewDevice === 'mobile' ? 'max-w-[340px]' : 'max-w-full'"
            >
                {{-- Mockup Window Chrome Header --}}
                <div class="flex items-center gap-2 border-b border-neutral-800 bg-neutral-900/90 px-3.5 py-2.5 text-xs text-neutral-400">
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-500/80"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-500/80"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500/80"></span>
                    </div>
                    <div class="flex-1 truncate rounded-md bg-neutral-800/80 px-2.5 py-0.5 text-center font-mono text-[10px] text-neutral-300">
                        <span x-text="'https://' + (form.name.toLowerCase().replace(/[^a-z0-9]/g, '') || 'mystore') + '.storefront.com'"></span>
                    </div>
                    <div class="shrink-0 flex items-center gap-1 text-neutral-400">
                        <i class="ph ph-lock text-xs"></i>
                    </div>
                </div>

                {{-- Mockup Viewport Body --}}
                <div class="bg-white text-neutral-900 select-none overflow-hidden text-xs">
                    {{-- 1. Dynamic Top Announcement Bar --}}
                    <div
                        class="flex items-center justify-between px-3 py-1.5 text-[11px] font-medium text-white transition-colors"
                        :style="'background-color: ' + form.primary"
                        x-show="form.announcement"
                    >
                        <div class="flex-1 text-center truncate pr-2">
                            <span x-text="form.announcement || 'Free shipping on orders over $50! ✨'"></span>
                        </div>
                        <button type="button" class="text-white/80 hover:text-white shrink-0">✕</button>
                    </div>

                    {{-- 2. Storefront Navigation Bar --}}
                    <div class="flex items-center justify-between border-b border-neutral-100 bg-white px-4 py-2.5">
                        {{-- Brand / Logo --}}
                        <div class="flex items-center gap-2">
                            <template x-if="getPreviewLogo()">
                                <img
                                    :src="getPreviewLogo()"
                                    alt="Logo"
                                    :style="'height: ' + Math.round((Number(form.logo_height) || 68) * 0.55) + 'px'"
                                    class="w-auto max-w-[140px] object-contain transition-all"
                                    x-on:error="$el.style.display='none'"
                                >
                            </template>
                            <span
                                x-show="!getPreviewLogo() || form.logo_show_name"
                                class="font-extrabold text-sm tracking-tight text-neutral-900 truncate max-w-[140px]"
                                x-text="form.name || 'Store Name'"
                            ></span>
                        </div>

                        {{-- Nav Links (Desktop) --}}
                        <div class="hidden sm:flex items-center gap-3 text-[11px] font-medium text-neutral-600" x-show="previewDevice === 'desktop'">
                            <span class="hover:text-neutral-900 cursor-pointer font-semibold" :style="'color: ' + form.primary">{{ __('Home') }}</span>
                            <span class="hover:text-neutral-900 cursor-pointer">{{ __('Shop') }}</span>
                            <span class="hover:text-neutral-900 cursor-pointer">{{ __('Collections') }}</span>
                            <span class="hover:text-neutral-900 cursor-pointer">{{ __('About') }}</span>
                        </div>

                        {{-- Action Icons --}}
                        <div class="flex items-center gap-2 text-neutral-600">
                            <i class="ph ph-magnifying-glass text-sm"></i>
                            <i class="ph ph-heart text-sm"></i>
                            <div class="relative cursor-pointer">
                                <i class="ph ph-shopping-bag text-base"></i>
                                <span
                                    class="absolute -top-1 -right-1 flex h-3.5 w-3.5 items-center justify-center rounded-full text-[9px] font-bold text-white"
                                    :style="'background-color: ' + form.primary"
                                >
                                    2
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- 3. Mock Hero Banner Section --}}
                    <div class="relative bg-gradient-to-br from-neutral-50 to-neutral-100/80 p-5 sm:p-6 border-b border-neutral-100 text-left">
                        <div class="space-y-2 max-w-[280px]">
                            <span
                                class="inline-block rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                :style="'background-color: ' + form.primary + '18; color: ' + form.primary"
                            >
                                {{ __('New Collection') }}
                            </span>
                            <h3 class="text-base sm:text-lg font-bold text-neutral-900 tracking-tight leading-snug">
                                {{ __('Essential Urban Fashion') }}
                            </h3>
                            <p class="text-[11px] text-neutral-500 leading-relaxed">
                                {{ __('Premium heavyweight fabrics, modern relaxed cuts, and handcrafted essentials.') }}
                            </p>
                            <div class="pt-1">
                                <button
                                    type="button"
                                    @mouseenter="btnHover = true"
                                    @mouseleave="btnHover = false"
                                    :style="'background-color: ' + (btnHover ? form.hover : form.primary)"
                                    class="rounded-lg px-4 py-1.5 text-xs font-semibold text-white shadow-xs transition-colors flex items-center gap-1.5"
                                >
                                    <span>{{ __('Explore Catalog') }}</span>
                                    <i class="ph ph-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Mock Product Grid Section --}}
                    <div class="p-4 bg-white space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-neutral-900 tracking-tight">{{ __('Trending Products') }}</span>
                            <span class="text-[10px] font-medium cursor-pointer" :style="'color: ' + form.primary">{{ __('View all') }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            {{-- Product 1 --}}
                            <div class="rounded-xl border border-neutral-100 p-2.5 space-y-1.5 bg-neutral-50/40">
                                <div class="h-20 w-full rounded-lg bg-neutral-200/70 flex items-center justify-center text-neutral-400">
                                    <i class="ph ph-t-shirt text-2xl"></i>
                                </div>
                                <div class="space-y-0.5">
                                    <div class="font-medium text-[11px] text-neutral-800 truncate">{{ __('Heavyweight Tee') }}</div>
                                    <div class="text-[11px] font-bold text-neutral-900">$38.00</div>
                                </div>
                                <button
                                    type="button"
                                    class="w-full rounded-md border py-1 text-[10px] font-semibold transition-colors"
                                    :style="'color: ' + form.primary + '; border-color: ' + form.primary + '50'"
                                >
                                    {{ __('Add to Cart') }}
                                </button>
                            </div>

                            {{-- Product 2 --}}
                            <div class="rounded-xl border border-neutral-100 p-2.5 space-y-1.5 bg-neutral-50/40">
                                <div class="h-20 w-full rounded-lg bg-neutral-200/70 flex items-center justify-center text-neutral-400">
                                    <i class="ph ph-hoodie text-2xl"></i>
                                </div>
                                <div class="space-y-0.5">
                                    <div class="font-medium text-[11px] text-neutral-800 truncate">{{ __('Oversized Hoodie') }}</div>
                                    <div class="text-[11px] font-bold text-neutral-900">$74.00</div>
                                </div>
                                <button
                                    type="button"
                                    class="w-full rounded-md border py-1 text-[10px] font-semibold transition-colors"
                                    :style="'color: ' + form.primary + '; border-color: ' + form.primary + '50'"
                                >
                                    {{ __('Add to Cart') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- 5. Mock Footer Bar --}}
                    <div
                        class="p-4 text-white/90 space-y-3 transition-colors"
                        :style="'background-color: ' + form.footer_color"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <span class="font-bold text-xs tracking-tight text-white block" x-text="form.name || 'Store Name'"></span>
                                <p class="text-[10px] text-white/70 line-clamp-2 leading-relaxed" x-text="form.footer_text || 'Premium wholesale & retail e-commerce platform.'"></p>
                                <template x-if="form.app_download_enabled">
                                    <div class="pt-2 space-y-1">
                                        <span class="text-[8px] font-bold uppercase tracking-wider text-white/70 block" x-text="form.app_download_title || 'Experience On Mobile'"></span>
                                        <div class="flex items-center gap-1.5">
                                            <div class="inline-flex items-center gap-1 rounded bg-black/40 border border-white/20 px-1.5 py-0.5 text-[8.5px] text-white font-medium">
                                                <i class="ph ph-apple-logo text-[10px]"></i>
                                                <span>App Store</span>
                                            </div>
                                            <div class="inline-flex items-center gap-1 rounded bg-black/40 border border-white/20 px-1.5 py-0.5 text-[8.5px] text-white font-medium">
                                                <i class="ph ph-google-play-logo text-[10px] text-emerald-400"></i>
                                                <span>Google Play</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <div class="text-[10px] text-white/70 space-y-1 shrink-0 text-right">
                                <template x-if="form.contact_email">
                                    <div class="flex items-center gap-1 justify-end">
                                        <i class="ph ph-envelope"></i>
                                        <span x-text="form.contact_email"></span>
                                    </div>
                                </template>
                                <template x-if="form.contact_phone">
                                    <div class="flex items-center gap-1 justify-end">
                                        <i class="ph ph-phone"></i>
                                        <span x-text="form.contact_phone"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="border-t border-white/10 pt-2 flex items-center justify-between text-[9px] text-white/50">
                            <span>© 2026 <span x-text="form.name || 'Store'"></span>. {{ __('All rights reserved.') }}</span>
                            <span class="flex items-center gap-1">
                                <i class="ph ph-shield-check"></i> {{ __('Secure SSL') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Live Preview Helper Notice --}}
            <div class="flex items-center gap-2.5 rounded-xl border border-neutral-200 bg-neutral-50/80 p-3 text-xs text-neutral-600 dark:border-neutral-800 dark:bg-neutral-800/40 dark:text-neutral-400">
                <i class="ph ph-sparkle text-base text-amber-500 shrink-0"></i>
                <span>{{ __('Live preview updates instantaneously as you type or pick colors. Click Save Settings to persist.') }}</span>
            </div>
        </div>
    </div>

    {{-- =====================================================================
         TAB 2: DOMAINS & ROUTING
         ===================================================================== --}}
    <div x-show="activeTab === 'domains'" x-cloak class="space-y-6">
        {{-- Intro Notice --}}
        <div class="rounded-2xl border border-blue-200/80 bg-blue-50/60 p-4 text-xs text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200">
            <div class="flex items-start gap-3">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-500 text-white shrink-0 mt-0.5">
                    <i class="ph ph-info text-base"></i>
                </span>
                <div class="space-y-0.5">
                    <p class="font-semibold text-sm">{{ __('Custom Domain Routing & SSL Provisioning') }}</p>
                    <p class="leading-relaxed">
                        {{ __('Point your custom domain (e.g. shop.example.com) to your storefront. Once DNS records are configured, verify the domain to activate it. Free SSL certificates are automatically handled.') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- 1. Default System Domain Card --}}
        @if($systemDomain)
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-neutral-100 pb-4 dark:border-neutral-800">
                    <div class="flex items-center gap-2.5">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                            <i class="ph ph-link-simple text-lg"></i>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="heading-4 text-title text-base font-semibold">{{ __('Default Subdomain (System Handle)') }}</h2>
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                    {{ __('Active') }}
                                </span>
                                @if($systemDomain->is_primary)
                                    <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-400">
                                        {{ __('Primary Domain') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-body">{{ __('Your built-in cloud URL that always works out of the box.') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="copyText('https://{{ $systemDomain->host }}', 'sys_url')"
                            class="btn btn-outline btn-sm rounded-lg text-xs flex items-center gap-1.5"
                        >
                            <i class="ph" :class="copiedKey === 'sys_url' ? 'ph-check text-emerald-600' : 'ph-copy'"></i>
                            <span x-text="copiedKey === 'sys_url' ? '{{ __('Copied!') }}' : '{{ __('Copy URL') }}'"></span>
                        </button>
                        <a
                            href="https://{{ $systemDomain->host }}"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-ghost btn-sm rounded-lg text-xs flex items-center gap-1.5"
                        >
                            <i class="ph ph-arrow-square-out text-sm"></i>
                            <span>{{ __('Open') }}</span>
                        </a>
                    </div>
                </div>

                {{-- Rename Store Handle Form --}}
                <div class="bg-neutral-50/70 rounded-xl p-4 border border-neutral-200/80 dark:bg-neutral-800/40 dark:border-neutral-700 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-neutral-800 dark:text-neutral-200">{{ __('Customize Subdomain Handle:') }}</span>
                        <span class="text-xs text-neutral-500 font-mono">{{ $systemDomain->host }}</span>
                    </div>
                    <form method="POST" action="{{ route('user.commerce.storefront.domains.rename', $systemDomain->id) }}" class="flex flex-col sm:flex-row items-center gap-2.5">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_tab" value="domains">
                        <div class="relative flex-1 w-full flex items-center">
                            <input
                                name="handle"
                                required
                                pattern="[a-z0-9][a-z0-9-]{2,40}"
                                value="{{ $systemDomain->handle }}"
                                aria-label="Store handle"
                                class="form-input rounded-r-none border-r-0 text-sm font-mono"
                                placeholder="my-shop"
                            >
                            <span class="inline-flex items-center rounded-r-sm border border-neutral-200 bg-neutral-100 px-3.5 py-3 text-xs text-neutral-600 font-mono dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400">
                                .{{ $rootDomain }}
                            </span>
                        </div>
                        <button type="submit" class="btn btn-outline btn-sm w-full sm:w-auto text-xs py-3 rounded-sm font-semibold">
                            <i class="ph ph-arrows-clockwise text-base"></i>
                            <span>{{ __('Update Handle') }}</span>
                        </button>
                    </form>
                    <p class="text-[11px] text-neutral-500">{{ __('3-40 lowercase letters, numbers, and hyphens. Result: [handle].' . $rootDomain) }}</p>
                </div>
            </div>
        @endif

        {{-- 2. Custom Domains List Card --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-5">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-4 dark:border-neutral-800">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                        <i class="ph ph-globe text-lg"></i>
                    </span>
                    <div>
                        <h2 class="heading-4 text-title text-base font-semibold">{{ __('Custom Domains') }}</h2>
                        <p class="text-xs text-body">{{ __('Connect your own branded domain or subdomain (e.g. shop.example.com).') }}</p>
                    </div>
                </div>
            </div>

            @forelse($customDomains as $domain)
                <div class="rounded-xl border border-neutral-200 bg-neutral-50/50 p-5 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2.5">
                                <span class="text-base font-bold text-neutral-900 dark:text-neutral-100 font-mono">{{ $domain->host }}</span>
                                @if($domain->status === 'active')
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center gap-1">
                                        <i class="ph ph-check-circle"></i> {{ __('Active & Verified') }}
                                    </span>
                                @elseif($domain->status === 'failed')
                                    <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-400 flex items-center gap-1">
                                        <i class="ph ph-warning-circle"></i> {{ __('Verification Failed') }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 flex items-center gap-1">
                                        <i class="ph ph-clock"></i> {{ __('Pending DNS Verification') }}
                                    </span>
                                @endif

                                @if($domain->is_primary)
                                    <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 flex items-center gap-1">
                                        <i class="ph ph-star-fill"></i> {{ __('Primary') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-neutral-500">
                                {{ __('Added on') }} {{ $domain->created_at?->format('M d, Y') }}
                            </p>
                            @if($domain->status !== 'active' && $domain->verification_error)
                                <p class="text-xs text-red-600 dark:text-red-400">
                                    @switch($domain->verification_error)
                                        @case('txt_missing') {{ __('TXT verification record not found yet.') }} @break
                                        @case('routing_missing') {{ __('Domain does not point to the storefront target yet.') }} @break
                                        @default {{ __('DNS target is not configured for this platform.') }}
                                    @endswitch
                                </p>
                            @endif
                        </div>

                        {{-- Domain Action Buttons --}}
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <form method="POST" action="{{ route('user.commerce.storefront.domains.verify', $domain->id) }}">
                                @csrf
                                <input type="hidden" name="_tab" value="domains">
                                <button type="submit" class="btn btn-outline btn-sm rounded-lg text-xs flex items-center gap-1.5 hover:border-primary">
                                    <i class="ph ph-arrows-clockwise text-sm"></i>
                                    <span>{{ __('Verify DNS Now') }}</span>
                                </button>
                            </form>

                            @if(!$domain->is_primary && $domain->status === 'active')
                                <form method="POST" action="{{ route('user.commerce.storefront.domains.primary', $domain->id) }}">
                                    @csrf
                                    <input type="hidden" name="_tab" value="domains">
                                    <button type="submit" class="btn btn-outline btn-sm rounded-lg text-xs flex items-center gap-1.5 hover:border-blue-500 text-blue-700 dark:text-blue-400">
                                        <i class="ph ph-star text-sm"></i>
                                        <span>{{ __('Set as Primary') }}</span>
                                    </button>
                                </form>
                            @endif

                            @if(!$domain->is_primary)
                                <form method="POST" action="{{ route('user.commerce.storefront.domains.delete', $domain->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="_tab" value="domains">
                                    <button
                                        type="submit"
                                        data-confirm
                                        data-confirm-title="{{ __('Remove Custom Domain?') }}"
                                        data-confirm-message="{{ __('Remove :host from your storefront? Traffic from this domain will stop routing to your store.', ['host' => $domain->host]) }}"
                                        data-confirm-button="{{ __('Remove Domain') }}"
                                        data-confirm-variant="danger"
                                        data-confirm-icon="ph-trash"
                                        class="btn btn-ghost btn-sm rounded-lg text-xs text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/40"
                                    >
                                        <i class="ph ph-trash text-sm"></i>
                                        <span>{{ __('Remove') }}</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    {{-- DNS Instruction Records Table --}}
                    <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-700 dark:bg-neutral-900 space-y-3">
                        <div class="flex items-center justify-between text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                            <span class="flex items-center gap-1.5">
                                <i class="ph ph-terminal-window text-base text-primary"></i>
                                {{ __('Required DNS Records for') }} <code class="text-primary">{{ $domain->host }}</code>:
                            </span>
                        </div>

                        <div class="space-y-2 text-xs font-mono">
                            {{-- CNAME Record --}}
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-lg bg-neutral-50 p-2.5 dark:bg-neutral-800/60">
                                <div class="flex items-center gap-3">
                                    <span class="rounded bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-800 dark:bg-blue-900 dark:text-blue-200">CNAME</span>
                                    <span class="text-neutral-800 dark:text-neutral-200">{{ $domain->host }}</span>
                                    <span class="text-neutral-400">→</span>
                                    <span class="text-neutral-700 dark:text-neutral-300 font-semibold">{{ $dnsTarget ?: 'STOREFRONT_DNS_TARGET' }}</span>
                                </div>
                                <button
                                    type="button"
                                    @click="copyText('{{ $dnsTarget ?: 'STOREFRONT_DNS_TARGET' }}', 'cname_{{ $domain->id }}')"
                                    class="text-neutral-500 hover:text-neutral-900 dark:hover:text-white flex items-center gap-1 text-[11px]"
                                >
                                    <i class="ph" :class="copiedKey === 'cname_{{ $domain->id }}' ? 'ph-check text-emerald-600' : 'ph-copy'"></i>
                                    <span x-text="copiedKey === 'cname_{{ $domain->id }}' ? '{{ __('Copied!') }}' : '{{ __('Copy Target') }}'"></span>
                                </button>
                            </div>

                            {{-- TXT Record --}}
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-lg bg-neutral-50 p-2.5 dark:bg-neutral-800/60">
                                <div class="flex items-center gap-3 truncate">
                                    <span class="rounded bg-purple-100 px-2 py-0.5 text-[10px] font-bold text-purple-800 dark:bg-purple-900 dark:text-purple-200">TXT</span>
                                    <span class="text-neutral-800 dark:text-neutral-200 truncate">_verify.{{ $domain->host }}</span>
                                    <span class="text-neutral-400">→</span>
                                    <span class="text-neutral-700 dark:text-neutral-300 font-semibold truncate">{{ $domain->verification_token }}</span>
                                </div>
                                <button
                                    type="button"
                                    @click="copyText('{{ $domain->verification_token }}', 'txt_{{ $domain->id }}')"
                                    class="text-neutral-500 hover:text-neutral-900 dark:hover:text-white flex items-center gap-1 text-[11px] shrink-0"
                                >
                                    <i class="ph" :class="copiedKey === 'txt_{{ $domain->id }}' ? 'ph-check text-emerald-600' : 'ph-copy'"></i>
                                    <span x-text="copiedKey === 'txt_{{ $domain->id }}' ? '{{ __('Copied!') }}' : '{{ __('Copy Token') }}'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-neutral-300 p-8 text-center dark:border-neutral-700 space-y-2">
                    <span class="grid h-12 w-12 place-items-center rounded-full bg-neutral-100 text-neutral-400 mx-auto dark:bg-neutral-800">
                        <i class="ph ph-globe text-2xl"></i>
                    </span>
                    <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-200">{{ __('No Custom Domains Connected') }}</h3>
                    <p class="text-xs text-neutral-500 max-w-md mx-auto">
                        {{ __('Add your custom domain below to link your own branded storefront address.') }}
                    </p>
                </div>
            @endforelse

            {{-- 3. Add Custom Domain Form --}}
            <div class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-3">
                <label class="form-label flex items-center gap-1.5 text-xs font-semibold text-neutral-800 dark:text-neutral-200 uppercase tracking-wider">
                    <i class="ph ph-globe text-base text-primary"></i>
                    <span>{{ __('Connect a New Custom Domain:') }}</span>
                </label>
                <form method="POST" action="{{ route('user.commerce.storefront.domains.store') }}" class="flex flex-col sm:flex-row gap-2.5">
                    @csrf
                    <input type="hidden" name="_tab" value="domains">
                    <input
                        required
                        name="host"
                        placeholder="shop.example.com"
                        class="form-input flex-1 text-sm font-mono"
                    >
                    <button type="submit" class="btn btn-primary rounded-lg px-5 py-2.5 text-xs font-semibold flex items-center justify-center gap-2">
                        <i class="ph ph-plus text-base"></i>
                        <span>{{ __('Add Custom Domain') }}</span>
                    </button>
                </form>
                <p class="text-[11px] text-neutral-500">
                    {{ __('Enter standard host format without http:// or trailing slashes (e.g. store.mybrand.com).') }}
                </p>
            </div>
        </div>
    </div>

    {{-- =====================================================================
         TAB 3: NAVIGATION MENUS (VISUAL BUILDER)
         ===================================================================== --}}
    <div x-show="activeTab === 'menus'" x-cloak class="space-y-6">
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-neutral-100 pb-4 dark:border-neutral-800">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                        <i class="ph ph-list-dashes text-lg"></i>
                    </span>
                    <div>
                        <h2 class="heading-4 text-title text-base font-semibold">{{ __('Storefront Navigation Menus') }}</h2>
                        <p class="text-xs text-body">{{ __('Configure Header, Footer, and Mobile menu links smoothly with the visual builder.') }}</p>
                    </div>
                </div>

                {{-- Slot Switcher Tabs --}}
                <div class="flex items-center rounded-lg border border-neutral-200 bg-neutral-100/70 p-1 dark:border-neutral-700 dark:bg-neutral-800">
                    @foreach($menuSlots as $slotKey => $slotMeta)
                        <button
                            type="button"
                            @click="activeMenuSlot = '{{ $slotKey }}'"
                            :class="activeMenuSlot === '{{ $slotKey }}' ? 'bg-white text-neutral-900 shadow-2xs font-semibold dark:bg-neutral-900 dark:text-white' : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                            class="rounded-md px-3 py-1.5 text-xs transition-colors flex items-center gap-1.5"
                        >
                            <i class="ph {{ $slotMeta['icon'] }} text-sm"></i>
                            <span>{{ $slotMeta['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Visual Link Builders per slot --}}
            @foreach(['header', 'footer', 'mobile'] as $slot)
                <div x-show="activeMenuSlot === '{{ $slot }}'" class="space-y-5">
                    <form method="POST" action="{{ route('user.commerce.storefront.menus') }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_tab" value="menus">
                        <input type="hidden" name="slot" value="{{ $slot }}">

                        {{-- Live Storefront Menu Bar Preview --}}
                        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-neutral-900 shadow-xs dark:border-neutral-700">
                            <div class="flex items-center justify-between border-b border-neutral-800 px-3.5 py-1.5 text-[11px] text-neutral-400">
                                <span class="flex items-center gap-1.5 font-semibold uppercase tracking-wider">
                                    <i class="ph ph-eye text-primary"></i>
                                    {{ __('Live') }} {{ ucfirst($slot) }} {{ __('Menu Preview') }}
                                </span>
                                <span class="font-mono text-[10px]" x-text="menuItems['{{ $slot }}'].length + ' {{ __('items configured') }}'"></span>
                            </div>
                            <div
                                class="flex flex-wrap items-center justify-between gap-4 px-4 py-3 text-white transition-colors"
                                :style="'background-color: ' + ( '{{ $slot }}' === 'footer' ? (form.footer_color || '#0A2540') : (form.primary || '#0866FF') )"
                            >
                                <div class="flex items-center gap-2 shrink-0">
                                    <template x-if="getPreviewLogo()">
                                        <img :src="getPreviewLogo()" alt="Logo" class="h-7 w-auto max-w-[110px] object-contain">
                                    </template>
                                    <span x-show="!getPreviewLogo() || form.logo_show_name" class="text-sm font-black tracking-tight" x-text="form.name || 'Store'"></span>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5">
                                    <template x-for="(pItem, pIdx) in menuItems['{{ $slot }}']" :key="'prev-' + pIdx">
                                        <div class="inline-flex items-center gap-1 rounded-lg bg-white/15 px-2.5 py-1 text-xs font-semibold text-white">
                                            <span x-text="pItem.label || 'Untitled'"></span>
                                            <template x-if="pItem.badge">
                                                <span class="rounded bg-amber-400 px-1 py-0.2 text-[9px] font-extrabold uppercase text-neutral-950" x-text="pItem.badge"></span>
                                            </template>
                                            <template x-if="(pItem.mega && pItem.mega !== 'none') || (pItem.children && pItem.children.length > 0)">
                                                <i class="ph ph-caret-down text-[10px] opacity-80"></i>
                                            </template>
                                        </div>
                                    </template>
                                    @if($slot !== 'footer')
                                        <span class="inline-flex items-center rounded-full border border-white/40 bg-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white">
                                            WHOLESALE
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Controls Bar --}}
                        <div class="flex flex-wrap items-center justify-between gap-3 bg-neutral-50/70 rounded-xl p-3.5 border border-neutral-200/80 dark:bg-neutral-800/40 dark:border-neutral-700">
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    @click="addMenuItem('{{ $slot }}', 'New Link', '/', '{{ $slot === 'footer' ? 'none' : 'none' }}')"
                                    class="btn btn-primary btn-sm rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-2xs"
                                >
                                    <i class="ph ph-plus text-sm"></i>
                                    <span>{{ __('Add Menu Link') }}</span>
                                </button>

                                {{-- Quick Presets & Mega-Menus --}}
                                <div class="relative" x-data="{ open: false }">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        class="btn btn-outline btn-sm rounded-lg text-xs font-semibold flex items-center gap-1.5 bg-white dark:bg-neutral-900"
                                    >
                                        <i class="ph ph-lightning text-amber-500"></i>
                                        <span>{{ __('Add Preset / Mega-Menu') }}</span>
                                        <i class="ph ph-caret-down text-xs"></i>
                                    </button>
                                    <div
                                        x-show="open"
                                        @click.outside="open = false"
                                        class="absolute left-0 mt-1 w-64 max-h-80 overflow-y-auto rounded-xl border border-neutral-200 bg-white py-1.5 shadow-xl z-30 text-xs dark:border-neutral-700 dark:bg-neutral-900 divide-y divide-neutral-100 dark:divide-neutral-800"
                                    >
                                        @if($slot !== 'footer')
                                            <div class="py-1">
                                                <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-neutral-400">{{ __('Mega-Menu Dropdowns') }}</div>
                                                <button type="button" @click="addMenuItem('{{ $slot }}', 'Brand', '/products', 'brand'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800 flex items-center justify-between">
                                                    <span class="font-semibold">Brand Showcase</span>
                                                    <span class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">Mega</span>
                                                </button>
                                                <button type="button" @click="addMenuItem('{{ $slot }}', 'Kids', '/category/kids', 'kids'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800 flex items-center justify-between">
                                                    <span class="font-semibold">Kids Collection</span>
                                                    <span class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">Mega</span>
                                                </button>
                                                <button type="button" @click="addMenuItem('{{ $slot }}', 'Man', '/category/men', 'man'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800 flex items-center justify-between">
                                                    <span class="font-semibold">Men&apos;s Apparel</span>
                                                    <span class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">Mega</span>
                                                </button>
                                                <button type="button" @click="addMenuItem('{{ $slot }}', 'Woman', '/category/women', 'woman'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800 flex items-center justify-between">
                                                    <span class="font-semibold">Women&apos;s Apparel</span>
                                                    <span class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">Mega</span>
                                                </button>
                                                <button type="button" @click="addMenuItem('{{ $slot }}', 'Accessories', '/category/accessories', 'accessories'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800 flex items-center justify-between">
                                                    <span class="font-semibold">Accessories</span>
                                                    <span class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">Mega</span>
                                                </button>
                                            </div>
                                        @endif

                                        <div class="py-1">
                                            <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-neutral-400">{{ __('Storefront Pages') }}</div>
                                            <button type="button" @click="addMenuItem('{{ $slot }}', 'Home', '/', 'none'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800">Home (/)</button>
                                            <button type="button" @click="addMenuItem('{{ $slot }}', 'All Products', '/products', 'none'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800">All Products (/products)</button>
                                            <button type="button" @click="addMenuItem('{{ $slot }}', 'New Arrivals', '/collections/new-arrivals', 'none', 'NEW'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800">New Arrivals (/collections/new-arrivals)</button>
                                            <button type="button" @click="addMenuItem('{{ $slot }}', 'Special Deals', '/collections/deals', 'none', 'SALE'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800">Special Deals (/collections/deals)</button>
                                            <button type="button" @click="addMenuItem('{{ $slot }}', 'Order Tracking', '/track-order', 'none'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800">Order Tracking (/track-order)</button>
                                            <button type="button" @click="addMenuItem('{{ $slot }}', 'Journal', '/blog', 'none'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800">Blog / Journal (/blog)</button>
                                            <button type="button" @click="addMenuItem('{{ $slot }}', 'FAQs', '/faqs', 'none'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800">FAQs (/faqs)</button>
                                            <button type="button" @click="addMenuItem('{{ $slot }}', 'Contact Us', '/contact', 'none'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800">Contact Us (/contact)</button>
                                        </div>

                                        @if(isset($categories) && $categories->whereNull('parent_id')->isNotEmpty())
                                            <div class="py-1">
                                                <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-neutral-400">{{ __('Your Store Categories') }}</div>
                                                @foreach($categories->whereNull('parent_id') as $cat)
                                                    <button
                                                        type="button"
                                                        @click="addMenuItem('{{ $slot }}', {{ Illuminate\Support\Js::from($cat->name) }}, '/category/{{ $cat->slug }}', 'auto'); open = false;"
                                                        class="w-full text-left px-3 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800 truncate"
                                                    >
                                                        {{ $cat->name }} (/category/{{ $cat->slug }})
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Restore Default Storefront Menu --}}
                                <button
                                    type="button"
                                    @click="resetDefaultMenu('{{ $slot }}')"
                                    class="btn btn-ghost btn-sm rounded-lg text-xs text-neutral-600 hover:text-primary dark:text-neutral-400 flex items-center gap-1"
                                    title="{{ __('Load the standard storefront menu items into the editor') }}"
                                >
                                    <i class="ph ph-arrow-counter-clockwise text-sm"></i>
                                    <span>{{ __('Load Default Menu') }}</span>
                                </button>
                            </div>

                            <button
                                type="button"
                                @click="menuRawMode['{{ $slot }}'] = !menuRawMode['{{ $slot }}']"
                                class="text-xs text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-300 flex items-center gap-1 font-mono"
                            >
                                <i class="ph ph-code"></i>
                                <span x-text="menuRawMode['{{ $slot }}'] ? '{{ __('Visual Mode') }}' : '{{ __('Raw JSON Mode') }}'"></span>
                            </button>
                        </div>

                        {{-- Hidden Serialized Input for form submission --}}
                        <textarea
                            name="items"
                            class="hidden"
                            :value="JSON.stringify(menuItems['{{ $slot }}'])"
                        ></textarea>

                        {{-- Visual Mode: Link Rows --}}
                        <div x-show="!menuRawMode['{{ $slot }}']" class="space-y-3">
                            <template x-for="(item, index) in menuItems['{{ $slot }}']" :key="index">
                                <div class="rounded-xl border border-neutral-200 bg-white p-3.5 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900 space-y-3">
                                    <div class="flex items-start gap-2.5">
                                        {{-- Reorder Handle --}}
                                        <div class="flex flex-col items-center gap-0.5 shrink-0 pt-1 text-neutral-400">
                                            <button
                                                type="button"
                                                @click="moveMenuItem('{{ $slot }}', index, index - 1)"
                                                :disabled="index === 0"
                                                class="hover:text-neutral-800 disabled:opacity-20 dark:hover:text-white"
                                                title="{{ __('Move up') }}"
                                            >
                                                <i class="ph ph-caret-up text-xs"></i>
                                            </button>
                                            <span class="font-mono text-[10px] font-bold text-neutral-400" x-text="'#' + (index + 1)"></span>
                                            <button
                                                type="button"
                                                @click="moveMenuItem('{{ $slot }}', index, index + 1)"
                                                :disabled="index === menuItems['{{ $slot }}'].length - 1"
                                                class="hover:text-neutral-800 disabled:opacity-20 dark:hover:text-white"
                                                title="{{ __('Move down') }}"
                                            >
                                                <i class="ph ph-caret-down text-xs"></i>
                                            </button>
                                        </div>

                                        {{-- Main Link Fields --}}
                                        <div class="grid grid-cols-1 {{ $slot === 'footer' ? 'sm:grid-cols-2' : 'sm:grid-cols-12' }} gap-2.5 flex-1">
                                            <div class="{{ $slot === 'footer' ? '' : 'sm:col-span-3' }}">
                                                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-400 mb-1">{{ __('Menu Label') }}</label>
                                                <input
                                                    type="text"
                                                    x-model="item.label"
                                                    placeholder="e.g. Brand, Kids, Men"
                                                    class="form-input form-input-sm text-xs font-semibold"
                                                    required
                                                >
                                            </div>
                                            <div class="{{ $slot === 'footer' ? '' : 'sm:col-span-4' }}">
                                                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-400 mb-1">{{ __('Destination URL / Path') }}</label>
                                                <input
                                                    type="text"
                                                    x-model="item.href"
                                                    placeholder="/products or /category/men"
                                                    class="form-input form-input-sm text-xs font-mono"
                                                    required
                                                >
                                            </div>
                                            @if($slot !== 'footer')
                                                <div class="sm:col-span-3">
                                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-400 mb-1">{{ __('Dropdown / Mega-Menu') }}</label>
                                                    <select x-model="item.mega" class="form-input form-input-sm text-xs">
                                                        <option value="auto">{{ __('Auto-detect from URL') }}</option>
                                                        <option value="none">{{ __('Direct Link (No Dropdown)') }}</option>
                                                        <option value="brand">{{ __('Brand Showcase Mega-Menu') }}</option>
                                                        <option value="kids">{{ __('Kids Category Mega-Menu') }}</option>
                                                        <option value="man">{{ __('Men Category Mega-Menu') }}</option>
                                                        <option value="woman">{{ __('Women Category Mega-Menu') }}</option>
                                                        <option value="accessories">{{ __('Accessories Mega-Menu') }}</option>
                                                        <option value="custom">{{ __('Custom Sub-Links Dropdown') }}</option>
                                                    </select>
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-400 mb-1">{{ __('Badge (Opt)') }}</label>
                                                    <input
                                                        type="text"
                                                        x-model="item.badge"
                                                        maxlength="15"
                                                        placeholder="e.g. NEW"
                                                        class="form-input form-input-sm text-xs uppercase"
                                                    >
                                                </div>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-1 pt-4 shrink-0">
                                            @if($slot !== 'footer')
                                                <button
                                                    type="button"
                                                    @click="addSubMenuItem('{{ $slot }}', index)"
                                                    class="rounded-lg border border-neutral-200 px-2 py-1.5 text-[11px] font-semibold text-neutral-600 hover:border-primary hover:text-primary dark:border-neutral-700 dark:text-neutral-300"
                                                    title="{{ __('Add nested dropdown link') }}"
                                                >
                                                    + {{ __('Sub-link') }}
                                                </button>
                                            @endif
                                            <button
                                                type="button"
                                                @click="removeMenuItem('{{ $slot }}', index)"
                                                class="rounded-lg p-2 text-neutral-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40"
                                                title="{{ __('Delete link') }}"
                                            >
                                                <i class="ph ph-trash text-base"></i>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Custom Nested Sub-Links --}}
                                    @if($slot !== 'footer')
                                        <div
                                            x-show="item.mega === 'custom' || (item.children && item.children.length > 0)"
                                            class="ml-7 rounded-lg border border-neutral-200/80 bg-neutral-50/70 p-3 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-2"
                                        >
                                            <div class="flex items-center justify-between">
                                                <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">
                                                    {{ __('Dropdown Sub-Links for') }} <span class="text-primary" x-text="item.label"></span>
                                                </span>
                                                <button
                                                    type="button"
                                                    @click="addSubMenuItem('{{ $slot }}', index)"
                                                    class="text-xs font-semibold text-primary hover:underline flex items-center gap-1"
                                                >
                                                    <i class="ph ph-plus"></i> {{ __('Add Sub-Link') }}
                                                </button>
                                            </div>

                                            <template x-for="(child, cIdx) in (item.children || [])" :key="cIdx">
                                                <div class="flex items-center gap-2">
                                                    <i class="ph ph-arrow-elbow-down-right text-neutral-400 text-xs"></i>
                                                    <input
                                                        type="text"
                                                        x-model="child.label"
                                                        placeholder="Sub-link label (e.g. Hoodies)"
                                                        class="form-input form-input-sm text-xs flex-1"
                                                        required
                                                    >
                                                    <input
                                                        type="text"
                                                        x-model="child.href"
                                                        placeholder="/category/men-tops"
                                                        class="form-input form-input-sm text-xs font-mono flex-1"
                                                        required
                                                    >
                                                    <button
                                                        type="button"
                                                        @click="removeSubMenuItem('{{ $slot }}', index, cIdx)"
                                                        class="p-1.5 text-neutral-400 hover:text-red-600"
                                                        title="{{ __('Remove sub-link') }}"
                                                    >
                                                        <i class="ph ph-x text-sm"></i>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    @endif
                                </div>
                            </template>

                            <div x-show="menuItems['{{ $slot }}'].length === 0" class="rounded-xl border border-dashed border-neutral-300 p-6 text-center text-xs text-neutral-500 dark:border-neutral-700 space-y-2">
                                <p>{{ __('No links configured for this menu.') }}</p>
                                <button
                                    type="button"
                                    @click="resetDefaultMenu('{{ $slot }}')"
                                    class="btn btn-outline btn-sm rounded-lg text-xs font-semibold inline-flex items-center gap-1.5"
                                >
                                    <i class="ph ph-arrow-counter-clockwise"></i>
                                    <span>{{ __('Load Standard Storefront Menu') }}</span>
                                </button>
                            </div>
                        </div>

                        {{-- Raw JSON Mode (for advanced power users) --}}
                        <div x-show="menuRawMode['{{ $slot }}']" class="space-y-2">
                            <textarea
                                rows="6"
                                class="form-input font-mono text-xs"
                                :value="JSON.stringify(menuItems['{{ $slot }}'], null, 2)"
                                @change="try { menuItems['{{ $slot }}'] = JSON.parse($event.target.value) } catch(e) { window.showAlertModal?.({ title: '{{ __('Invalid Menu JSON') }}', message: '{{ __('Please provide a valid JSON array of menu link objects with label and href fields.') }}', variant: 'danger' }) }"
                            ></textarea>
                            <p class="text-[11px] text-neutral-500">{{ __('Each link object must have "label" and "href".') }}</p>
                        </div>

                        <div class="flex items-center justify-between border-t border-neutral-100 pt-4 dark:border-neutral-800">
                            <span class="text-xs text-neutral-500">
                                {{ __('Saving immediately updates your live storefront navigation.') }}
                            </span>
                            <button type="submit" class="btn btn-primary rounded-xl px-5 py-2.5 text-xs font-semibold flex items-center gap-1.5">
                                <i class="ph ph-check text-base"></i>
                                <span>{{ __('Save') }} {{ ucfirst($slot) }} {{ __('Menu') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

    {{-- =====================================================================
         TAB 4: HERO SLIDERS (VISUAL SLIDE CARDS)
         ===================================================================== --}}
    <div x-show="activeTab === 'sliders'" x-cloak class="space-y-6">
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-neutral-100 pb-4 dark:border-neutral-800">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                        <i class="ph ph-slideshow text-lg"></i>
                    </span>
                    <div>
                        <h2 class="heading-4 text-title text-base font-semibold">{{ __('Hero Banner Sliders') }}</h2>
                        <p class="text-xs text-body">{{ __('Create captivating carousel banners for the top of your homepage and category pages.') }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <select x-model="activeSliderId" class="form-input form-input-sm text-xs font-semibold">
                        @foreach($sliders as $slider)
                            <option value="{{ $slider->id }}">Slider #{{ $slider->id }}: {{ $slider->name }} ({{ count($slider->slides ?? []) }} slides)</option>
                        @endforeach
                        <option value="new">+ {{ __('Create New Slider') }}</option>
                    </select>
                </div>
            </div>

            {{-- Slider Editors --}}
            @foreach($sliders->concat([null]) as $slider)
                @php
                    $sId = $slider ? (string) $slider->id : 'new';
                @endphp
                <div x-show="String(activeSliderId) === '{{ $sId }}'" class="space-y-5">
                    <form
                        method="POST"
                        action="{{ $slider ? route('user.commerce.storefront.sliders.update', $slider->id) : route('user.commerce.storefront.sliders.store') }}"
                        class="space-y-5"
                    >
                        @csrf
                        @if($slider) @method('PUT') @endif
                        <input type="hidden" name="_tab" value="sliders">

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                            <div class="sm:col-span-2">
                                <label class="form-label" for="slider_name_{{ $sId }}">{{ __('Slider Name') }} <span class="text-red-500">*</span></label>
                                <input
                                    id="slider_name_{{ $sId }}"
                                    name="name"
                                    x-model="sliderData['{{ $sId }}'].name"
                                    required
                                    maxlength="150"
                                    class="form-input"
                                    placeholder="e.g. Main Homepage Hero"
                                >
                            </div>
                            <div class="flex items-center justify-end gap-2 pb-1">
                                <button
                                    type="button"
                                    @click="addSlide('{{ $sId }}')"
                                    class="btn btn-outline btn-sm rounded-lg text-xs font-semibold flex items-center gap-1.5"
                                >
                                    <i class="ph ph-plus text-sm"></i>
                                    <span>{{ __('Add New Slide') }}</span>
                                </button>
                            </div>
                        </div>

                        {{-- Hidden Serialized slides input --}}
                        <textarea
                            name="slides"
                            class="hidden"
                            :value="serializeSlides('{{ $sId }}')"
                        ></textarea>

                        {{-- Visual Slide Cards --}}
                        <div class="space-y-4">
                            <template x-for="(slide, sIdx) in sliderData['{{ $sId }}'].slides" :key="ensureSlideUid(slide)">
                                <div
                                    :data-slide-uid="slide._uid"
                                    class="scroll-mt-24 rounded-xl border bg-neutral-50/50 p-3 shadow-2xs transition dark:bg-neutral-800/40"
                                    :class="isSlideOpen('{{ $sId }}', slide) ? 'border-primary/50 ring-1 ring-primary/20 space-y-4' : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300'"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                        :class="isSlideOpen('{{ $sId }}', slide) ? 'border-b border-neutral-200/80 pb-3 dark:border-neutral-700' : ''"
                                    >
                                        <button
                                            type="button"
                                            @click="toggleSlide('{{ $sId }}', slide)"
                                            class="flex min-w-0 flex-1 items-center gap-3 text-left"
                                            :aria-expanded="isSlideOpen('{{ $sId }}', slide)"
                                        >
                                            <i class="ph ph-caret-right text-sm text-neutral-400 transition-transform" :class="isSlideOpen('{{ $sId }}', slide) ? 'rotate-90' : ''"></i>
                                            <span class="relative flex h-10 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md border border-neutral-200 bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-800">
                                                <template x-if="slide.image">
                                                    <img :src="slide.image" alt="" class="h-full w-full object-cover" loading="lazy">
                                                </template>
                                                <template x-if="!slide.image && slide.video && /\.(mp4|webm|mov|m4v)(\?|$)/i.test(slide.video)">
                                                    <video :src="slide.video" class="h-full w-full object-cover" muted preload="metadata"></video>
                                                </template>
                                                <i x-show="!slide.image && !slide.video" class="ph ph-image text-base text-neutral-400"></i>
                                                <i x-show="slide.video" class="ph-fill ph-play-circle absolute text-lg text-white drop-shadow"></i>
                                            </span>
                                            <span class="flex min-w-0 flex-wrap items-center gap-2">
                                            <span class="rounded bg-neutral-200 px-2 py-0.5 text-xs font-bold text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200" x-text="'Slide #' + (sIdx + 1)"></span>
                                            <span class="text-xs font-semibold text-neutral-800 dark:text-neutral-200 truncate" x-text="slide.title || 'Untitled Slide'"></span>
                                            <span
                                                x-show="slide.video"
                                                class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-semibold text-purple-700 dark:bg-purple-950/60 dark:text-purple-300"
                                            >
                                                <i class="ph-fill ph-video-camera"></i>
                                                <span>{{ __('Video Slide') }}</span>
                                            </span>
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                                :class="slide.active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-neutral-200 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300'"
                                                x-text="slide.active ? '{{ __('Active') }}' : '{{ __('Hidden') }}'"
                                            ></span>
                                            <span x-show="!slide.image" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">
                                                <i class="ph ph-warning"></i>
                                                <span>{{ __('Image needed') }}</span>
                                            </span>
                                            </span>
                                        </button>

                                        <div class="flex shrink-0 items-center gap-1">
                                            <label class="mr-1 inline-flex cursor-pointer items-center" title="{{ __('Show on storefront') }}">
                                                <input type="checkbox" x-model="slide.active" class="peer sr-only">
                                                <span class="relative h-5 w-9 rounded-full bg-neutral-300 transition after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:bg-primary peer-checked:after:translate-x-4 dark:bg-neutral-600"></span>
                                            </label>
                                            <button
                                                type="button"
                                                @click="moveSlide('{{ $sId }}', sIdx, sIdx - 1)"
                                                :disabled="sIdx === 0"
                                                class="rounded p-1 text-neutral-400 hover:text-neutral-800 disabled:opacity-20 dark:hover:text-white"
                                                title="{{ __('Move up') }}"
                                            >
                                                <i class="ph ph-arrow-up text-sm"></i>
                                            </button>
                                            <button
                                                type="button"
                                                @click="moveSlide('{{ $sId }}', sIdx, sIdx + 1)"
                                                :disabled="sIdx === sliderData['{{ $sId }}'].slides.length - 1"
                                                class="rounded p-1 text-neutral-400 hover:text-neutral-800 disabled:opacity-20 dark:hover:text-white"
                                                title="{{ __('Move down') }}"
                                            >
                                                <i class="ph ph-arrow-down text-sm"></i>
                                            </button>
                                            <button
                                                type="button"
                                                @click="duplicateSlide('{{ $sId }}', sIdx)"
                                                class="rounded p-1 text-neutral-400 hover:text-neutral-800 dark:hover:text-white"
                                                title="{{ __('Duplicate slide') }}"
                                            >
                                                <i class="ph ph-copy text-sm"></i>
                                            </button>
                                            <button
                                                type="button"
                                                x-show="confirmDeleteSlide !== slide._uid"
                                                @click="confirmDeleteSlide = slide._uid"
                                                class="rounded p-1 text-neutral-400 hover:text-red-600"
                                                title="{{ __('Remove slide') }}"
                                            >
                                                <i class="ph ph-trash text-sm"></i>
                                            </button>
                                            <span x-show="confirmDeleteSlide === slide._uid" class="flex items-center gap-1 text-[11px] font-medium">
                                                <span class="text-neutral-600 dark:text-neutral-300">{{ __('Delete?') }}</span>
                                                <button type="button" @click="removeSlide('{{ $sId }}', sIdx)" class="rounded bg-red-600 px-1.5 py-0.5 text-white hover:bg-red-700">{{ __('Yes') }}</button>
                                                <button type="button" @click="confirmDeleteSlide = null" class="rounded px-1.5 py-0.5 text-neutral-600 hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-700">{{ __('No') }}</button>
                                            </span>
                                        </div>
                                    </div>

                                    <div x-show="isSlideOpen('{{ $sId }}', slide)" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
                                        {{-- Image / Video Thumbnail Preview & Quick Actions --}}
                                        <div class="md:col-span-4 space-y-2.5">
                                            <div class="h-36 w-full rounded-xl border border-neutral-200 bg-neutral-100 overflow-hidden relative dark:border-neutral-700 dark:bg-neutral-800 flex items-center justify-center group">
                                                <template x-if="slide.video && /\.(mp4|webm|mov|m4v)(\?|$)/i.test(slide.video)">
                                                    <video :src="slide.video" :poster="slide.image || null" class="absolute inset-0 h-full w-full object-cover" muted loop autoplay playsinline></video>
                                                </template>
                                                <template x-if="slide.image && !(slide.video && /\.(mp4|webm|mov|m4v)(\?|$)/i.test(slide.video))">
                                                    <img :src="slide.image" alt="Slide" class="h-full w-full object-cover" x-on:error="$el.style.display='none'">
                                                </template>
                                                <div x-show="!slide.image && !slide.video" class="text-center p-3 text-neutral-400">
                                                    <i class="ph ph-image text-2xl mb-1 block"></i>
                                                    <span class="text-[11px]">{{ __('No image selected') }}</span>
                                                </div>
                                                <div
                                                    x-show="slide.video"
                                                    class="absolute bottom-2 left-2 right-2 rounded-lg bg-black/75 px-2.5 py-1 text-[10px] font-medium text-white flex items-center gap-1.5 backdrop-blur-xs truncate"
                                                >
                                                    <i class="ph-fill ph-play-circle text-sm text-emerald-400 shrink-0"></i>
                                                    <span class="truncate" x-text="slide.video"></span>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-1.5">
                                                <button
                                                    type="button"
                                                    @click="openGalleryForSlide('{{ $sId }}', sIdx, 'image', 'image')"
                                                    class="btn btn-outline btn-sm text-xs font-semibold rounded-lg flex items-center justify-center gap-1.5 py-1.5"
                                                >
                                                    <i class="ph ph-images text-sm text-primary"></i>
                                                    <span>{{ __('Choose Image') }}</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="openGalleryForSlide('{{ $sId }}', sIdx, 'video', 'video')"
                                                    class="btn btn-outline btn-sm text-xs font-semibold rounded-lg flex items-center justify-center gap-1.5 py-1.5"
                                                >
                                                    <i class="ph ph-video-camera text-sm text-purple-600"></i>
                                                    <span>{{ __('Choose Video') }}</span>
                                                </button>
                                            </div>

                                            <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-neutral-700 dark:text-neutral-300 pt-0.5">
                                                <input type="checkbox" x-model="slide.active" class="rounded text-primary focus:ring-primary">
                                                <span>{{ __('Slide Active') }}</span>
                                            </label>
                                        </div>

                                        {{-- Slide Fields --}}
                                        <div class="md:col-span-8 space-y-3">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="form-label text-xs">{{ __('Slide Title') }} *</label>
                                                    <input type="text" x-model="slide.title" data-slide-title required class="form-input form-input-sm text-xs" placeholder="Summer Flash Sale">
                                                </div>
                                                <div>
                                                    <label class="form-label text-xs">{{ __('Subtitle / Description') }}</label>
                                                    <input type="text" x-model="slide.subtitle" class="form-input form-input-sm text-xs" placeholder="Up to 50% off on all seasonal arrivals.">
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <div class="flex items-center justify-between mb-1">
                                                        <label class="form-label text-xs mb-0">{{ __('Desktop Image / Video Poster') }} *</label>
                                                        <button
                                                            type="button"
                                                            @click="openGalleryForSlide('{{ $sId }}', sIdx, 'image', 'image')"
                                                            class="text-[11px] font-medium text-primary hover:underline flex items-center gap-1"
                                                        >
                                                            <i class="ph ph-images text-xs"></i>
                                                            <span>{{ __('Media Library') }}</span>
                                                        </button>
                                                    </div>
                                                    <input type="text" x-model="slide.image" required class="form-input form-input-sm text-xs font-mono" placeholder="https://... or choose from gallery">
                                                </div>

                                                <div>
                                                    <div class="flex items-center justify-between mb-1">
                                                        <label class="form-label text-xs mb-0">{{ __('Mobile Image (Optional)') }}</label>
                                                        <button
                                                            type="button"
                                                            @click="openGalleryForSlide('{{ $sId }}', sIdx, 'mobile_image', 'image')"
                                                            class="text-[11px] font-medium text-primary hover:underline flex items-center gap-1"
                                                        >
                                                            <i class="ph ph-device-mobile text-xs"></i>
                                                            <span>{{ __('Choose Mobile') }}</span>
                                                        </button>
                                                    </div>
                                                    <input type="text" x-model="slide.mobile_image" class="form-input form-input-sm text-xs font-mono" placeholder="Optional portrait image for mobile">
                                                </div>
                                            </div>

                                            <div>
                                                <div class="flex items-center justify-between mb-1">
                                                    <label class="form-label text-xs mb-0">
                                                        {{ __('Background Video URL (Optional — MP4, WebM, or YouTube)') }}
                                                    </label>
                                                    <div class="flex items-center gap-2">
                                                        <button
                                                            type="button"
                                                            @click="openGalleryForSlide('{{ $sId }}', sIdx, 'video', 'video')"
                                                            class="text-[11px] font-medium text-purple-600 hover:underline flex items-center gap-1 dark:text-purple-400"
                                                        >
                                                            <i class="ph ph-video-camera text-xs"></i>
                                                            <span>{{ __('Upload / Select Video') }}</span>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            x-show="slide.video"
                                                            @click="slide.video = ''"
                                                            class="text-[11px] font-medium text-red-600 hover:underline"
                                                        >
                                                            {{ __('Clear') }}
                                                        </button>
                                                    </div>
                                                </div>
                                                <input
                                                    type="text"
                                                    x-model="slide.video"
                                                    class="form-input form-input-sm text-xs font-mono"
                                                    placeholder="https://.../hero.mp4 or https://www.youtube.com/watch?v=..."
                                                >
                                                <p class="mt-1 text-[11px] text-neutral-500">
                                                    {{ __('Leave empty for an image slide, or paste an MP4/WebM/YouTube URL (or pick a video from Media Library) to play a full-bleed background video.') }}
                                                </p>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="form-label text-xs">{{ __('Call to Action Text') }}</label>
                                                    <input type="text" x-model="slide.button_text" class="form-input form-input-sm text-xs" placeholder="Shop Now">
                                                </div>
                                                <div>
                                                    <label class="form-label text-xs">{{ __('Button Link') }}</label>
                                                    <input type="text" x-model="slide.button_link" class="form-input form-input-sm text-xs font-mono" placeholder="/products">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div x-show="sliderData['{{ $sId }}'].slides.length === 0" class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-xs text-neutral-500 dark:border-neutral-700 space-y-2">
                                <i class="ph ph-image text-2xl text-neutral-400"></i>
                                <p>{{ __('No slides in this slider yet. Click "Add New Slide" above to start designing.') }}</p>
                            </div>
                        </div>

                        <div class="flex justify-end pt-3">
                            <button type="submit" class="btn btn-primary rounded-xl px-6 py-2.5 text-xs font-semibold flex items-center gap-2">
                                <i class="ph ph-check text-base"></i>
                                <span>{{ $slider ? __('Save Slider') : __('Create Slider') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

    {{-- =====================================================================
         TAB 5: PAGES & CMS (CONTENT PAGES)
         ===================================================================== --}}
    <div x-show="activeTab === 'pages'" x-cloak class="space-y-6">
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-neutral-100 pb-4 dark:border-neutral-800">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                        <i class="ph ph-browsers text-lg"></i>
                    </span>
                    <div>
                        <h2 class="heading-4 text-title text-base font-semibold">{{ __('Storefront Pages & Section Builder') }}</h2>
                        <p class="text-xs text-body">{{ __('Create custom landing pages, homepages, policies, and editorial sections.') }}</p>
                    </div>
                </div>
            </div>

            {{-- Pages Table / Grid --}}
            <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @foreach($pages as $page)
                    <div class="py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2.5">
                                <a href="{{ route('user.commerce.storefront.pages.edit', $page->id) }}" class="text-sm font-bold text-neutral-900 hover:text-primary dark:text-neutral-100 transition-colors">
                                    {{ $page->title }}
                                </a>
                                <code class="rounded bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 font-mono dark:bg-neutral-800 dark:text-neutral-400">
                                    /{{ $page->slug }}
                                </code>
                                @if($page->status === 'published')
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center gap-1">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        {{ __('Published') }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">
                                        {{ __('Draft') }}
                                    </span>
                                @endif

                                <span class="rounded bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                                    {{ $page->sections->count() }} {{ __('sections') }}
                                </span>
                            </div>
                            <p class="text-xs text-neutral-400">
                                @if($page->published_at)
                                    {{ __('Last published') }} {{ $page->published_at->diffForHumans() }}
                                @else
                                    {{ __('Unpublished draft') }}
                                @endif
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <a
                                href="{{ route('user.commerce.storefront.pages.edit', $page->id) }}"
                                class="btn btn-outline btn-sm rounded-lg text-xs font-semibold flex items-center gap-1"
                            >
                                <i class="ph ph-squares-four text-sm"></i>
                                <span>{{ __('Open Builder') }}</span>
                            </a>

                            <form method="POST" action="{{ route('user.commerce.storefront.pages.preview', $page->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm rounded-lg text-xs flex items-center gap-1">
                                    <i class="ph ph-eye text-sm"></i>
                                    <span>{{ __('Preview') }}</span>
                                </button>
                            </form>

                            <form method="POST" action="{{ route('user.commerce.storefront.pages.publish', $page->id) }}">
                                @csrf
                                <input type="hidden" name="_tab" value="pages">
                                <button type="submit" class="btn btn-primary btn-sm rounded-lg text-xs flex items-center gap-1">
                                    <i class="ph ph-cloud-arrow-up text-sm"></i>
                                    <span>{{ __('Publish Live') }}</span>
                                </button>
                            </form>

                            @if($page->slug !== 'home')
                                <form method="POST" action="{{ route('user.commerce.storefront.pages.destroy', $page->id) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this page?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="_tab" value="pages">
                                    <button type="submit" class="btn btn-ghost btn-sm rounded-lg text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 p-2" title="{{ __('Delete Page') }}">
                                        <i class="ph ph-trash text-sm"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Quick Create Page Card --}}
            <div class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-3">
                <span class="text-xs font-semibold text-neutral-800 dark:text-neutral-200 uppercase tracking-wider block">
                    {{ __('Create New Storefront Page:') }}
                </span>
                <form
                    method="POST"
                    action="{{ route('user.commerce.storefront.pages.store') }}"
                    class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end"
                    x-data="{ title: '', slug: '', makeSlug() { this.slug = this.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''); } }"
                >
                    @csrf
                    <input type="hidden" name="_tab" value="pages">
                    <input type="hidden" name="sections" value="[]">
                    <div class="sm:col-span-6">
                        <label class="form-label text-xs">{{ __('Page Title') }} *</label>
                        <input
                            required
                            name="title"
                            x-model="title"
                            @input="makeSlug()"
                            placeholder="e.g. Summer Lookbook 2026"
                            class="form-input text-xs"
                        >
                    </div>
                    <div class="sm:col-span-4">
                        <label class="form-label text-xs">{{ __('URL Slug') }} *</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400 font-mono text-xs">/</span>
                            <input
                                required
                                name="slug"
                                x-model="slug"
                                placeholder="summer-lookbook"
                                class="form-input pl-6 text-xs font-mono"
                            >
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="btn btn-primary w-full rounded-lg py-3 text-xs font-semibold flex items-center justify-center gap-1.5">
                            <i class="ph ph-plus text-sm"></i>
                            <span>{{ __('Create Page') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- =====================================================================
         TAB 6: COUPONS & DISCOUNTS
         ===================================================================== --}}
    <div x-show="activeTab === 'coupons'" x-cloak x-data="{ editingCoupon: null, copiedCouponId: null }" class="space-y-6">
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-6">
            {{-- Header & Metrics --}}
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-neutral-100 pb-5 dark:border-neutral-800">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-primary">
                        <i class="ph ph-ticket text-xl"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="heading-4 text-title text-base font-semibold">{{ __('Storefront Coupon Codes & Promotions') }}</h2>
                            <span class="rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-semibold text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                {{ $coupons->where('active', true)->count() }} {{ __('Active') }} / {{ $coupons->count() }} {{ __('Total') }}
                            </span>
                        </div>
                        <p class="text-xs text-body">{{ __('Create discount coupons with fixed amount or percentage deductions, limit usage, and track redemption.') }}</p>
                    </div>
                </div>

                <a href="#create-coupon-section" class="btn btn-primary rounded-xl px-4 py-2 text-xs font-semibold flex items-center gap-1.5 shadow-2xs">
                    <i class="ph ph-plus text-sm"></i>
                    <span>{{ __('Create New Coupon') }}</span>
                </a>
            </div>

            {{-- Existing Coupons List --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($coupons as $coupon)
                    @php
                        $discountDisplay = $coupon->type === 'percentage'
                            ? ((float) $coupon->value) . '% OFF'
                            : '$' . number_format((float) $coupon->value, 2) . ' OFF';
                        $minOrderDisplay = $coupon->min_order ? '$' . number_format((float) $coupon->min_order, 2) : __('No Minimum');
                        $maxDiscountDisplay = $coupon->max_discount ? '$' . number_format((float) $coupon->max_discount, 2) : __('No Cap');
                    @endphp
                    <div class="relative overflow-hidden rounded-2xl border {{ $coupon->active ? 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900' : 'border-neutral-200/60 dark:border-neutral-800/60 bg-neutral-50/70 dark:bg-neutral-900/40 opacity-85' }} p-5 shadow-xs transition hover:shadow-sm space-y-4">
                        {{-- Top Bar: Code, Discount Badge, and Status Toggle --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-lg border border-dashed border-primary/40 bg-primary/10 px-3 py-1 font-mono text-sm font-bold text-primary tracking-wider">
                                    {{ $coupon->code }}
                                </span>

                                <button
                                    type="button"
                                    @click="navigator.clipboard.writeText('{{ $coupon->code }}'); copiedCouponId = {{ $coupon->id }}; setTimeout(() => { if (copiedCouponId === {{ $coupon->id }}) copiedCouponId = null; }, 2000)"
                                    class="inline-flex items-center gap-1 rounded-md border border-neutral-200 bg-white px-2 py-1 text-[11px] font-semibold text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 shadow-2xs transition dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300"
                                    title="{{ __('Copy Coupon Code') }}"
                                >
                                    <i :class="copiedCouponId === {{ $coupon->id }} ? 'ph ph-check text-emerald-600 font-bold' : 'ph ph-copy text-body'"></i>
                                    <span x-text="copiedCouponId === {{ $coupon->id }} ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                                </button>

                                <span class="rounded-md bg-neutral-100 px-2.5 py-1 text-xs font-bold text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200">
                                    {{ $discountDisplay }}
                                </span>
                            </div>

                            {{-- Active/Inactive Toggle Pill Form --}}
                            <form method="POST" action="{{ route('user.commerce.storefront.coupons.toggle', $coupon->id) }}">
                                @csrf
                                <input type="hidden" name="_tab" value="coupons">
                                <button
                                    type="submit"
                                    title="{{ $coupon->active ? __('Click to deactivate') : __('Click to activate') }}"
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition {{ $coupon->active ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-400' : 'bg-neutral-100 text-neutral-500 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-400' }}"
                                >
                                    <span class="size-2 rounded-full {{ $coupon->active ? 'bg-emerald-500' : 'bg-neutral-400' }}"></span>
                                    <span>{{ $coupon->active ? __('Active') : __('Inactive') }}</span>
                                    <i class="ph {{ $coupon->active ? 'ph-toggle-right text-base text-emerald-600' : 'ph-toggle-left text-base text-neutral-400' }}"></i>
                                </button>
                            </form>
                        </div>

                        {{-- Details Grid --}}
                        <div class="grid grid-cols-2 gap-2.5 rounded-xl border border-neutral-100 bg-neutral-50/70 p-3 text-xs dark:border-neutral-800/80 dark:bg-neutral-800/40">
                            <div>
                                <span class="text-body text-[11px] block">{{ __('Min Order') }}</span>
                                <span class="font-semibold text-neutral-800 dark:text-neutral-200">{{ $minOrderDisplay }}</span>
                            </div>
                            <div>
                                <span class="text-body text-[11px] block">{{ __('Max Discount') }}</span>
                                <span class="font-semibold text-neutral-800 dark:text-neutral-200">{{ $maxDiscountDisplay }}</span>
                            </div>
                            <div>
                                <span class="text-body text-[11px] block">{{ __('Usage Limit') }}</span>
                                <span class="font-semibold text-neutral-800 dark:text-neutral-200">{{ $coupon->usage_limit ? $coupon->usage_limit . ' uses' : __('Unlimited') }}</span>
                            </div>
                            <div>
                                <span class="text-body text-[11px] block">{{ __('Per Customer') }}</span>
                                <span class="font-semibold text-neutral-800 dark:text-neutral-200">{{ $coupon->per_customer_limit ? $coupon->per_customer_limit . ' per customer' : __('Unlimited') }}</span>
                            </div>
                        </div>

                        {{-- Action Buttons Toolbar --}}
                        <div class="flex items-center justify-between border-t border-neutral-100 pt-3 dark:border-neutral-800">
                            <button
                                type="button"
                                @click="editingCoupon = {
                                    id: {{ $coupon->id }},
                                    code: {{ Illuminate\Support\Js::from($coupon->code) }},
                                    type: {{ Illuminate\Support\Js::from($coupon->type) }},
                                    value: {{ (float) $coupon->value }},
                                    min_order: {{ $coupon->min_order ? (float) $coupon->min_order : 'null' }},
                                    max_discount: {{ $coupon->max_discount ? (float) $coupon->max_discount : 'null' }},
                                    usage_limit: {{ $coupon->usage_limit ? (int) $coupon->usage_limit : 'null' }},
                                    per_customer_limit: {{ $coupon->per_customer_limit ? (int) $coupon->per_customer_limit : 'null' }},
                                    active: {{ $coupon->active ? 'true' : 'false' }}
                                }"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900 transition dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                            >
                                <i class="ph ph-pencil-simple text-sm text-primary"></i>
                                <span>{{ __('Edit') }}</span>
                            </button>

                            <div class="flex items-center gap-2">
                                {{-- Status Toggle Button --}}
                                <form method="POST" action="{{ route('user.commerce.storefront.coupons.toggle', $coupon->id) }}">
                                    @csrf
                                    <input type="hidden" name="_tab" value="coupons">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 transition dark:text-neutral-400 dark:hover:bg-neutral-800"
                                    >
                                        <i class="ph {{ $coupon->active ? 'ph-power' : 'ph-check-circle' }}"></i>
                                        <span>{{ $coupon->active ? __('Disable') : __('Enable') }}</span>
                                    </button>
                                </form>

                                {{-- Delete Button --}}
                                <form method="POST" action="{{ route('user.commerce.storefront.coupons.delete', $coupon->id) }}" onsubmit="return confirm('{{ __('Delete coupon :code? This action cannot be undone.', ['code' => $coupon->code]) }}')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="_tab" value="coupons">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 hover:text-red-700 transition dark:hover:bg-red-950/30"
                                        title="{{ __('Delete Coupon') }}"
                                    >
                                        <i class="ph ph-trash text-sm"></i>
                                        <span>{{ __('Delete') }}</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-2 rounded-2xl border border-dashed border-neutral-300 p-10 text-center space-y-2 dark:border-neutral-700">
                        <span class="mx-auto grid size-12 place-items-center rounded-full bg-neutral-100 text-neutral-400 dark:bg-neutral-800">
                            <i class="ph ph-ticket text-2xl"></i>
                        </span>
                        <h4 class="font-semibold text-neutral-700 dark:text-neutral-300 text-sm">{{ __('No coupons created yet') }}</h4>
                        <p class="text-xs text-body max-w-sm mx-auto">{{ __('Fill out the form below to create your first discount code for your storefront customers.') }}</p>
                    </div>
                @endforelse
            </div>

            {{-- Create New Coupon Form --}}
            <div id="create-coupon-section" class="rounded-2xl border border-neutral-200 bg-neutral-50/70 p-6 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-4">
                <div class="flex items-center gap-2 border-b border-neutral-200/60 pb-3 dark:border-neutral-700/60">
                    <span class="grid size-7 place-items-center rounded-lg bg-primary/10 text-primary">
                        <i class="ph ph-plus-circle text-base"></i>
                    </span>
                    <span class="text-xs font-bold text-neutral-800 dark:text-neutral-200 uppercase tracking-wider block">
                        {{ __('Create New Coupon Code') }}
                    </span>
                </div>

                <form method="POST" action="{{ route('user.commerce.storefront.coupons.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    @csrf
                    <input type="hidden" name="_tab" value="coupons">
                    <div>
                        <label class="form-label text-xs">{{ __('Coupon Code') }} *</label>
                        <input required name="code" placeholder="SAVE20" class="form-input text-xs font-mono uppercase" style="text-transform: uppercase;">
                    </div>
                    <div>
                        <label class="form-label text-xs">{{ __('Discount Type') }} *</label>
                        <select name="type" class="form-input text-xs">
                            <option value="percentage">{{ __('Percentage (%)') }}</option>
                            <option value="fixed">{{ __('Fixed Amount ($)') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-xs">{{ __('Discount Value') }} *</label>
                        <input required type="number" step="any" min="0" name="value" placeholder="20" class="form-input text-xs">
                    </div>
                    <div>
                        <label class="form-label text-xs">{{ __('Min Order ($)') }}</label>
                        <input type="number" step="any" min="0" name="min_order" placeholder="50" class="form-input text-xs">
                    </div>
                    <div>
                        <label class="form-label text-xs">{{ __('Max Discount ($)') }}</label>
                        <input type="number" step="any" min="0" name="max_discount" placeholder="100" class="form-input text-xs">
                    </div>
                    <div>
                        <label class="form-label text-xs">{{ __('Total Usage Limit') }}</label>
                        <input type="number" min="1" name="usage_limit" placeholder="500" class="form-input text-xs">
                    </div>
                    <div>
                        <label class="form-label text-xs">{{ __('Per Customer Limit') }}</label>
                        <input type="number" min="1" name="per_customer_limit" placeholder="1" class="form-input text-xs">
                    </div>
                    <div>
                        <input type="hidden" name="active" value="0">
                        <label class="flex items-center gap-2 cursor-pointer pb-3 text-xs font-semibold">
                            <input type="checkbox" name="active" value="1" checked class="rounded text-primary focus:ring-primary">
                            <span>{{ __('Active Immediately') }}</span>
                        </label>
                    </div>
                    <div class="sm:col-span-4 flex justify-end pt-2">
                        <button type="submit" class="btn btn-primary rounded-xl px-6 py-2.5 text-xs font-semibold flex items-center gap-1.5 shadow-2xs">
                            <i class="ph ph-plus text-base"></i>
                            <span>{{ __('Save & Publish Coupon') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Coupon Modal --}}
        <div
            x-show="editingCoupon !== null"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @click.self="editingCoupon = null"
            @keydown.escape.window="editingCoupon = null"
        >
            <div
                x-show="editingCoupon !== null"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="w-full max-w-xl overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-xl dark:border-neutral-800 dark:bg-neutral-900"
            >
                <div class="flex items-center justify-between border-b border-neutral-100 px-6 py-4 dark:border-neutral-800">
                    <div class="flex items-center gap-2.5">
                        <span class="grid size-8 place-items-center rounded-lg bg-primary/10 text-primary">
                            <i class="ph ph-pencil-simple text-base"></i>
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-neutral-900 dark:text-white">
                                {{ __('Edit Coupon') }}: <span class="font-mono text-primary" x-text="editingCoupon?.code"></span>
                            </h3>
                            <p class="text-xs text-body">{{ __('Update discount rules, limits, and status.') }}</p>
                        </div>
                    </div>
                    <button type="button" @click="editingCoupon = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-800">
                        <i class="ph ph-x text-lg"></i>
                    </button>
                </div>

                <template x-if="editingCoupon">
                    <form
                        method="POST"
                        :action="'{{ route('user.commerce.storefront.coupons.update', ['id' => 999999]) }}'.replace('999999', editingCoupon.id)"
                        class="p-6 space-y-4"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_tab" value="coupons">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label text-xs">{{ __('Coupon Code') }} *</label>
                                <input required name="code" x-model="editingCoupon.code" class="form-input text-xs font-mono uppercase" style="text-transform: uppercase;">
                            </div>
                            <div>
                                <label class="form-label text-xs">{{ __('Discount Type') }} *</label>
                                <select name="type" x-model="editingCoupon.type" class="form-input text-xs">
                                    <option value="percentage">{{ __('Percentage (%)') }}</option>
                                    <option value="fixed">{{ __('Fixed Amount ($)') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label text-xs">{{ __('Discount Value') }} *</label>
                                <input required type="number" step="any" min="0" name="value" x-model="editingCoupon.value" class="form-input text-xs">
                            </div>
                            <div>
                                <label class="form-label text-xs">{{ __('Min Order ($)') }}</label>
                                <input type="number" step="any" min="0" name="min_order" x-model="editingCoupon.min_order" placeholder="0" class="form-input text-xs">
                            </div>
                            <div>
                                <label class="form-label text-xs">{{ __('Max Discount ($)') }}</label>
                                <input type="number" step="any" min="0" name="max_discount" x-model="editingCoupon.max_discount" placeholder="0" class="form-input text-xs">
                            </div>
                            <div>
                                <label class="form-label text-xs">{{ __('Total Usage Limit') }}</label>
                                <input type="number" min="1" name="usage_limit" x-model="editingCoupon.usage_limit" placeholder="Unlimited" class="form-input text-xs">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label text-xs">{{ __('Per Customer Limit') }}</label>
                                <input type="number" min="1" name="per_customer_limit" x-model="editingCoupon.per_customer_limit" placeholder="Unlimited" class="form-input text-xs">
                            </div>
                            <div class="sm:col-span-2">
                                <input type="hidden" name="active" value="0">
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold">
                                    <input type="checkbox" name="active" value="1" :checked="editingCoupon.active" class="rounded text-primary focus:ring-primary">
                                    <span>{{ __('Coupon Active & Redeemable') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                            <button
                                type="button"
                                @click="editingCoupon = null"
                                class="rounded-xl border border-neutral-200 px-4 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                            >
                                {{ __('Cancel') }}
                            </button>
                            <button
                                type="submit"
                                class="btn btn-primary rounded-xl px-5 py-2 text-xs font-semibold shadow-2xs"
                            >
                                {{ __('Save Changes') }}
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>


    {{-- =====================================================================
         TAB 7: REVIEWS MODERATION
         ===================================================================== --}}
    <div x-show="activeTab === 'reviews'" x-cloak class="space-y-6">
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-6">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-4 dark:border-neutral-800">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                        <i class="ph ph-star text-lg"></i>
                    </span>
                    <div>
                        <h2 class="heading-4 text-title text-base font-semibold">{{ __('Customer Product Reviews Moderation') }}</h2>
                        <p class="text-xs text-body">{{ __('Approve or reject customer submitted reviews before they appear publicly.') }}</p>
                    </div>
                </div>
                <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-bold text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                    {{ $reviews->count() }} {{ __('Pending') }}
                </span>
            </div>

            <div class="space-y-3">
                @forelse($reviews as $review)
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50/60 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                {{-- Stars --}}
                                <div class="flex items-center text-amber-400 text-sm">
                                    @for($i = 1; $i <= 5; $i++)
                                        <i class="ph {{ $i <= $review->rating ? 'ph-star-fill' : 'ph-star' }}"></i>
                                    @endfor
                                </div>
                                <span class="text-xs font-bold text-neutral-700 dark:text-neutral-300">{{ $review->rating }}/5</span>
                                <span class="text-neutral-300 dark:text-neutral-600">•</span>
                                <span class="text-xs text-neutral-500">{{ $review->created_at?->diffForHumans() }}</span>
                            </div>
                            <p class="text-xs text-neutral-800 dark:text-neutral-200 leading-relaxed font-medium">
                                "{{ $review->body }}"
                            </p>
                        </div>

                        <form method="POST" action="{{ route('user.commerce.storefront.reviews.update', $review->id) }}" class="flex items-center gap-2 shrink-0">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_tab" value="reviews">
                            <button
                                type="submit"
                                name="status"
                                value="approved"
                                class="btn btn-outline btn-sm rounded-lg text-xs text-emerald-700 hover:bg-emerald-50 hover:border-emerald-500 dark:text-emerald-400 dark:hover:bg-emerald-950/40 flex items-center gap-1"
                            >
                                <i class="ph ph-check text-sm"></i>
                                <span>{{ __('Approve') }}</span>
                            </button>
                            <button
                                type="submit"
                                name="status"
                                value="rejected"
                                class="btn btn-ghost btn-sm rounded-lg text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 flex items-center gap-1"
                            >
                                <i class="ph ph-x text-sm"></i>
                                <span>{{ __('Reject') }}</span>
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-xs text-neutral-500 dark:border-neutral-700 space-y-2">
                        <span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-50 text-emerald-600 mx-auto dark:bg-emerald-950/40">
                            <i class="ph ph-check-circle text-xl"></i>
                        </span>
                        <p class="font-semibold text-neutral-800 dark:text-neutral-200">{{ __('Review queue is clear!') }}</p>
                        <p>{{ __('No customer reviews are currently waiting for moderation.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- =====================================================================
         TAB 8: JOURNAL, FAQS & CURATED CONTENT
         ===================================================================== --}}
    @include('commerce::user.storefront-journal-faqs')

    {{-- =====================================================================
         TAB: CUSTOMER INQUIRIES & CONTACT MESSAGES
         ===================================================================== --}}
    @include('commerce::user.storefront-inquiries')

    {{-- =====================================================================
         TAB 9: API & DEPLOYMENT (.ENV) CREDENTIALS
         ===================================================================== --}}
    @include('commerce::user.storefront-chat-channels')
    @include('commerce::user.storefront-tracking')

    <div x-show="activeTab === 'deployment'" x-cloak class="space-y-6">
        {{-- 1. Ready-to-Use Next.js Storefront .env Block --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-neutral-100 pb-4 dark:border-neutral-800">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary/10 text-primary">
                        <i class="ph ph-terminal-window text-xl"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="heading-4 text-title text-base font-semibold">{{ __('Storefront Environment Configuration (storefront/.env)') }}</h2>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                {{ __('Auto-Provisioned & Active') }}
                            </span>
                        </div>
                        <p class="text-xs text-body">
                            {{ __('Copy or download this complete .env file and place it in your Next.js storefront/ directory before running npm run build && npm run start.') }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="showSecrets = !showSecrets"
                        class="btn btn-outline btn-sm rounded-lg text-xs font-semibold flex items-center gap-1.5"
                    >
                        <i class="ph" :class="showSecrets ? 'ph-eye-slash' : 'ph-eye'"></i>
                        <span x-text="showSecrets ? '{{ __('Hide Secrets') }}' : '{{ __('Reveal Secrets') }}'"></span>
                    </button>

                    <button
                        type="button"
                        @click="downloadEnv({{ Illuminate\Support\Js::from($storefrontEnvContent) }}, '.env')"
                        class="btn btn-outline btn-sm rounded-lg text-xs font-semibold flex items-center gap-1.5"
                    >
                        <i class="ph ph-download-simple text-sm"></i>
                        <span>{{ __('Download .env') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="copyText({{ Illuminate\Support\Js::from($storefrontEnvContent) }}, 'full_storefront_env')"
                        class="btn btn-primary btn-sm rounded-lg text-xs font-semibold flex items-center gap-1.5"
                    >
                        <i class="ph" :class="copiedKey === 'full_storefront_env' ? 'ph-check' : 'ph-copy'"></i>
                        <span x-text="copiedKey === 'full_storefront_env' ? '{{ __('Copied .env!') }}' : '{{ __('Copy Full .env') }}'"></span>
                    </button>

                    <form
                        method="POST"
                        action="{{ route('user.commerce.storefront.credentials.generate') }}"
                    >
                        @csrf
                        <input type="hidden" name="_tab" value="deployment">
                        <input type="hidden" name="target" value="all">
                        <button
                            type="submit"
                            data-confirm
                            data-confirm-title="{{ __('Regenerate All Secret Keys?') }}"
                            data-confirm-message="{{ __('Regenerate all 4 storefront secret keys? You will need to update your deployed storefront .env with the new keys.') }}"
                            data-confirm-button="{{ __('Regenerate All Keys') }}"
                            data-confirm-variant="warning"
                            data-confirm-icon="ph-arrows-clockwise"
                            class="btn btn-outline btn-sm rounded-lg text-xs font-semibold flex items-center gap-1.5 text-amber-700 hover:border-amber-500 dark:text-amber-400"
                        >
                            <i class="ph ph-arrows-clockwise text-sm"></i>
                            <span>{{ __('Regenerate All Keys') }}</span>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Code Block Preview --}}
            <div class="rounded-xl border border-neutral-800 bg-neutral-950 p-4 text-xs font-mono text-neutral-100 overflow-x-auto space-y-1.5">
                @foreach($envVariablesList as $envItem)
                    <div class="flex items-center gap-1 whitespace-nowrap">
                        <span class="text-emerald-400 font-semibold">{{ $envItem['key'] }}</span><span class="text-neutral-500">=</span>
                        @if($envItem['secret'])
                            <span x-show="showSecrets" class="text-amber-200">{{ $envItem['value'] }}</span>
                            <span x-show="!showSecrets" class="text-neutral-400">{{ substr($envItem['value'], 0, 8) . str_repeat('•', 48) . substr($envItem['value'], -6) }}</span>
                        @else
                            <span class="text-sky-300">{{ $envItem['value'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Individual Variables Breakdown Table --}}
            <div class="space-y-3 pt-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-500">{{ __('Individual Environment Variables & Key Rotation') }}</h3>
                <div class="divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
                    @foreach($envVariablesList as $envItem)
                        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <code class="rounded bg-neutral-100 px-2 py-0.5 text-xs font-bold text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100">{{ $envItem['key'] }}</code>
                                    @if($envItem['secret'])
                                        <span class="rounded bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950/50 dark:text-amber-400">
                                            {{ __('256-bit Secret') }}
                                        </span>
                                    @else
                                        <span class="rounded bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 dark:bg-blue-950/50 dark:text-blue-400">
                                            {{ __('Endpoint URL') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-body">{{ $envItem['description'] }}</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-1.5 font-mono text-xs text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 max-w-xs truncate">
                                    @if($envItem['secret'])
                                        <span x-show="showSecrets">{{ $envItem['value'] }}</span>
                                        <span x-show="!showSecrets">{{ substr($envItem['value'], 0, 6) . '••••••••••••••••' . substr($envItem['value'], -4) }}</span>
                                    @else
                                        <span>{{ $envItem['value'] }}</span>
                                    @endif
                                </div>

                                <button
                                    type="button"
                                    @click="copyText({{ Illuminate\Support\Js::from($envItem['value']) }}, {{ Illuminate\Support\Js::from('var_' . $envItem['key']) }})"
                                    class="btn btn-outline btn-sm rounded-lg text-xs font-semibold flex items-center gap-1"
                                >
                                    <i class="ph" :class="copiedKey === {{ Illuminate\Support\Js::from('var_' . $envItem['key']) }} ? 'ph-check text-emerald-600' : 'ph-copy'"></i>
                                    <span x-text="copiedKey === {{ Illuminate\Support\Js::from('var_' . $envItem['key']) }} ? '{{ __('Copied') }}' : '{{ __('Copy Value') }}'"></span>
                                </button>

                                @if($envItem['rotate_target'])
                                    <form method="POST" action="{{ route('user.commerce.storefront.credentials.generate') }}">
                                        @csrf
                                        <input type="hidden" name="_tab" value="deployment">
                                        <input type="hidden" name="target" value="{{ $envItem['rotate_target'] }}">
                                        <button
                                            type="submit"
                                            data-confirm
                                            data-confirm-title="{{ __('Rotate :key?', ['key' => $envItem['key']]) }}"
                                            data-confirm-message="{{ __('Generate a new 256-bit secret for :key? You will need to update this variable in your deployed storefront .env.', ['key' => $envItem['key']]) }}"
                                            data-confirm-button="{{ __('Rotate Key') }}"
                                            data-confirm-variant="warning"
                                            data-confirm-icon="ph-arrows-clockwise"
                                            class="btn btn-ghost btn-sm rounded-lg text-xs text-neutral-600 hover:text-amber-700 dark:text-neutral-400"
                                            title="{{ __('Rotate this secret key') }}"
                                        >
                                            <i class="ph ph-arrows-clockwise text-sm"></i>
                                            <span>{{ __('Rotate') }}</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- 2. Configure Storefront Connection URLs & DNS Target --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <div class="lg:col-span-7 rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-6">
                {{-- Header --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-primary shrink-0 shadow-2xs">
                            <i class="ph ph-plugs-connected text-xl"></i>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="heading-4 text-title text-base font-bold">{{ __('Connection URLs & Server Endpoints') }}</h2>
                                <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                    <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    {{ __('Live Endpoints') }}
                                </span>
                            </div>
                            <p class="text-xs text-body mt-0.5">{{ __('Configure the URLs used inside your generated .env file and for instant cache revalidation webhooks.') }}</p>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('user.commerce.storefront.credentials.update') }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_tab" value="deployment">

                    {{-- Group 1: Backend API & Media Delivery --}}
                    <div class="space-y-3.5 rounded-xl border border-neutral-150 bg-neutral-50/50 p-4 dark:border-neutral-800/80 dark:bg-neutral-800/20">
                        <div class="flex items-center justify-between border-b border-neutral-200/60 pb-2.5 dark:border-neutral-800">
                            <div class="flex items-center gap-2">
                                <span class="grid h-5 w-5 place-items-center rounded-md bg-neutral-200/70 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300 text-xs">
                                    <i class="ph ph-database text-xs"></i>
                                </span>
                                <span class="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200">{{ __('Backend API & Media Service') }}</span>
                            </div>
                            <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500">{{ __('WaPro Laravel Core') }}</span>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {{-- MARKETING_API_URL --}}
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label for="marketing_api_url" class="text-xs font-semibold text-neutral-900 dark:text-neutral-100 flex items-center gap-1">
                                        <span>{{ __('Backend API URL') }}</span>
                                        <span class="text-rose-500 font-bold">*</span>
                                    </label>
                                    <code class="rounded bg-white px-1.5 py-0.5 text-[10px] font-mono font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300 border border-neutral-200/70 dark:border-neutral-700/70">
                                        MARKETING_API_URL
                                    </code>
                                </div>
                                <div class="relative flex items-center">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400">
                                        <i class="ph ph-link-simple text-sm"></i>
                                    </span>
                                    <input
                                        id="marketing_api_url"
                                        type="url"
                                        name="marketing_api_url"
                                        required
                                        value="{{ old('marketing_api_url', $marketingApiUrl) }}"
                                        placeholder="https://app.yourdomain.com"
                                        class="form-input input-with-leading-icon @if(!empty($marketingApiUrl)) input-with-trailing-icon !pr-10 @else pr-3 @endif w-full !pl-10 text-xs font-mono tracking-tight rounded-lg border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                        style="padding-left: 2.5rem !important; @if(!empty($marketingApiUrl)) padding-right: 2.5rem !important; @endif"
                                        x-ref="marketingApiInput"
                                    >
                                    @if(!empty($marketingApiUrl))
                                        <a
                                            href="{{ $marketingApiUrl }}"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="absolute right-2 p-1 text-neutral-400 hover:text-primary transition-colors flex items-center justify-center"
                                            title="{{ __('Test Backend URL in new tab') }}"
                                        >
                                            <i class="ph ph-arrow-square-out text-sm"></i>
                                        </a>
                                    @endif
                                </div>
                                @error('marketing_api_url')
                                    <p class="text-[11px] text-rose-500">{{ $message }}</p>
                                @else
                                    <p class="flex items-center gap-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                                        <i class="ph ph-info text-xs shrink-0 text-neutral-400"></i>
                                        <span>{{ __('URL where this dashboard is reachable by Next.js.') }}</span>
                                    </p>
                                @enderror
                            </div>

                            {{-- STOREFRONT_MEDIA_ORIGIN --}}
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label for="media_origin" class="text-xs font-semibold text-neutral-900 dark:text-neutral-100 flex items-center gap-1">
                                        <span>{{ __('Media Origin URL') }}</span>
                                        <span class="text-rose-500 font-bold">*</span>
                                    </label>
                                    <code class="rounded bg-white px-1.5 py-0.5 text-[10px] font-mono font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300 border border-neutral-200/70 dark:border-neutral-700/70">
                                        STOREFRONT_MEDIA_ORIGIN
                                    </code>
                                </div>
                                <div class="relative flex items-center">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400">
                                        <i class="ph ph-image text-sm"></i>
                                    </span>
                                    <input
                                        id="media_origin"
                                        type="url"
                                        name="media_origin"
                                        required
                                        value="{{ old('media_origin', $mediaOrigin) }}"
                                        placeholder="https://app.yourdomain.com"
                                        class="form-input input-with-leading-icon input-with-trailing-action w-full !pl-10 !pr-20 text-xs font-mono tracking-tight rounded-lg border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                        style="padding-left: 2.5rem !important; padding-right: 4.75rem !important;"
                                        x-ref="mediaOriginInput"
                                    >
                                    <button
                                        type="button"
                                        @click="$refs.mediaOriginInput.value = $refs.marketingApiInput.value; $refs.mediaOriginInput.dispatchEvent(new Event('input'))"
                                        class="absolute right-1.5 px-2 py-0.5 text-[10px] font-semibold text-primary hover:bg-primary/10 rounded transition-colors flex items-center gap-1 cursor-pointer"
                                        title="{{ __('Sync with Backend API URL') }}"
                                    >
                                        <i class="ph ph-arrows-clockwise text-xs"></i>
                                        <span>{{ __('Sync') }}</span>
                                    </button>
                                </div>
                                @error('media_origin')
                                    <p class="text-[11px] text-rose-500">{{ $message }}</p>
                                @else
                                    <p class="flex items-center gap-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                                        <i class="ph ph-info text-xs shrink-0 text-neutral-400"></i>
                                        <span>{{ __('Usually identical to Backend API URL for image proxies.') }}</span>
                                    </p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Group 2: Next.js Storefront & Custom Domains --}}
                    <div class="space-y-3.5 rounded-xl border border-neutral-150 bg-neutral-50/50 p-4 dark:border-neutral-800/80 dark:bg-neutral-800/20">
                        <div class="flex items-center justify-between border-b border-neutral-200/60 pb-2.5 dark:border-neutral-800">
                            <div class="flex items-center gap-2">
                                <span class="grid h-5 w-5 place-items-center rounded-md bg-neutral-200/70 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300 text-xs">
                                    <i class="ph ph-globe text-xs"></i>
                                </span>
                                <span class="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200">{{ __('Storefront App & Domain Infrastructure') }}</span>
                            </div>
                            <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500">{{ __('Headless Next.js & DNS') }}</span>
                        </div>

                        <div class="space-y-4">
                            {{-- STOREFRONT_URL (Full Width to completely prevent truncation) --}}
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label for="storefront_url" class="text-xs font-semibold text-neutral-900 dark:text-neutral-100 flex items-center gap-1">
                                        <span>{{ __('Next.js Storefront URL') }}</span>
                                        <span class="text-rose-500 font-bold">*</span>
                                    </label>
                                    <code class="rounded bg-white px-1.5 py-0.5 text-[10px] font-mono font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300 border border-neutral-200/70 dark:border-neutral-700/70">
                                        STOREFRONT_URL
                                    </code>
                                </div>
                                <div class="relative flex items-center">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400">
                                        <i class="ph ph-browsers text-sm"></i>
                                    </span>
                                    <input
                                        id="storefront_url"
                                        type="url"
                                        name="storefront_url"
                                        required
                                        value="{{ old('storefront_url', $storefrontAppUrl) }}"
                                        placeholder="https://store.yourdomain.com"
                                        class="form-input input-with-leading-icon @if(!empty($storefrontAppUrl)) input-with-trailing-icon !pr-10 @else pr-3 @endif w-full !pl-10 text-xs font-mono tracking-tight rounded-lg border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                        style="padding-left: 2.5rem !important; @if(!empty($storefrontAppUrl)) padding-right: 2.5rem !important; @endif"
                                    >
                                    @if(!empty($storefrontAppUrl))
                                        <a
                                            href="{{ $storefrontAppUrl }}"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="absolute right-2 p-1 text-neutral-400 hover:text-primary transition-colors flex items-center justify-center"
                                            title="{{ __('Visit Storefront URL in new tab') }}"
                                        >
                                            <i class="ph ph-arrow-square-out text-sm"></i>
                                        </a>
                                    @endif
                                </div>
                                @error('storefront_url')
                                    <p class="text-[11px] text-rose-500">{{ $message }}</p>
                                @else
                                    <p class="flex items-center gap-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                                        <i class="ph ph-info text-xs shrink-0 text-neutral-400"></i>
                                        <span>{{ __('Target destination for on-demand ISR cache revalidation & live page previews.') }}</span>
                                    </p>
                                @enderror
                            </div>

                            {{-- STOREFRONT_ROOT_DOMAIN & STOREFRONT_DNS_TARGET (2 Columns: generous space to prevent truncation) --}}
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- STOREFRONT_ROOT_DOMAIN --}}
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label for="root_domain" class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">
                                            {{ __('Root Domain') }}
                                        </label>
                                        <code class="rounded bg-white px-1.5 py-0.5 text-[10px] font-mono font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300 border border-neutral-200/70 dark:border-neutral-700/70">
                                            STOREFRONT_ROOT_DOMAIN
                                        </code>
                                    </div>
                                    <div class="relative flex items-center">
                                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400">
                                            <i class="ph ph-at text-sm"></i>
                                        </span>
                                        <input
                                            id="root_domain"
                                            type="text"
                                            name="root_domain"
                                            value="{{ old('root_domain', $rootDomain) }}"
                                            placeholder="fashionfiest.com"
                                            class="form-input input-with-leading-icon w-full !pl-10 pr-3 text-xs font-mono tracking-tight rounded-lg border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                            style="padding-left: 2.5rem !important;"
                                        >
                                    </div>
                                    @error('root_domain')
                                        <p class="text-[11px] text-rose-500">{{ $message }}</p>
                                    @else
                                        <p class="flex items-center gap-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                                            <i class="ph ph-info text-xs shrink-0 text-neutral-400"></i>
                                            <span>{{ __('Base tenant domain for [store_slug].[root_domain].') }}</span>
                                        </p>
                                    @enderror
                                </div>

                                {{-- STOREFRONT_DNS_TARGET --}}
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label for="dns_target" class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">
                                            {{ __('CNAME Target') }}
                                        </label>
                                        <code class="rounded bg-white px-1.5 py-0.5 text-[10px] font-mono font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300 border border-neutral-200/70 dark:border-neutral-700/70">
                                            STOREFRONT_DNS_TARGET
                                        </code>
                                    </div>
                                    <div class="relative flex items-center">
                                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400">
                                            <i class="ph ph-share-network text-sm"></i>
                                        </span>
                                        <input
                                            id="dns_target"
                                            type="text"
                                            name="dns_target"
                                            value="{{ old('dns_target', $dnsTarget) }}"
                                            placeholder="cname.vercel-dns.com"
                                            class="form-input input-with-leading-icon w-full !pl-10 pr-3 text-xs font-mono tracking-tight rounded-lg border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900 focus:border-primary focus:ring-1 focus:ring-primary/20"
                                            style="padding-left: 2.5rem !important;"
                                        >
                                    </div>
                                    @error('dns_target')
                                        <p class="text-[11px] text-rose-500">{{ $message }}</p>
                                    @else
                                        <p class="flex items-center gap-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                                            <i class="ph ph-info text-xs shrink-0 text-neutral-400"></i>
                                            <span>{{ __('Shown in custom domain DNS setup instructions.') }}</span>
                                        </p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Footer & Action Button --}}
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                        <div class="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                            <span class="size-2 rounded-full bg-emerald-500 shrink-0"></span>
                            <span>{{ __('Endpoints dynamically update your storefront/.env export above.') }}</span>
                        </div>

                        <button type="submit" class="w-full sm:w-auto btn btn-primary btn-sm rounded-xl px-6 py-2.5 text-xs font-semibold shadow-xs hover:shadow transition-all flex items-center justify-center gap-2 cursor-pointer">
                            <i class="ph ph-floppy-disk text-base"></i>
                            <span>{{ __('Save Connection Endpoints') }}</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- 3. Deployment & Serve Guide --}}
            <div class="lg:col-span-5 rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-4">
                <div class="flex items-center gap-2.5 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                        <i class="ph ph-rocket-launch text-lg"></i>
                    </span>
                    <div>
                        <h2 class="heading-4 text-title text-base font-semibold">{{ __('How to Deploy & Serve Storefront') }}</h2>
                        <p class="text-xs text-body">{{ __('Run your headless Next.js storefront locally or in production.') }}</p>
                    </div>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="space-y-1.5">
                        <p class="font-semibold text-title">{{ __('Step 1: Save .env in storefront/ directory') }}</p>
                        <p class="text-body">{{ __('Click "Copy Full .env" or "Download .env" above and save it as storefront/.env (or paste into Vercel / Docker environment variables).') }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-title">{{ __('Step 2: Install, Build & Serve (Production)') }}</p>
                            <button
                                type="button"
                                @click="copyText('cd storefront\nnpm ci\nnpm run build\nnpm run start', 'deploy_cmd')"
                                class="text-primary hover:underline text-[11px] font-semibold flex items-center gap-1"
                            >
                                <i class="ph" :class="copiedKey === 'deploy_cmd' ? 'ph-check' : 'ph-copy'"></i>
                                <span x-text="copiedKey === 'deploy_cmd' ? '{{ __('Copied!') }}' : '{{ __('Copy Commands') }}'"></span>
                            </button>
                        </div>
                        <pre class="rounded-xl bg-neutral-950 p-3 font-mono text-[11px] text-neutral-200 overflow-x-auto">cd storefront
npm ci
npm run build
npm run start</pre>
                    </div>

                    <div class="space-y-1.5 pt-1">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-title">{{ __('Optional: WaPro Backend .env Variables') }}</p>
                            <button
                                type="button"
                                @click="copyText({{ Illuminate\Support\Js::from($backendEnvContent) }}, 'backend_env')"
                                class="text-primary hover:underline text-[11px] font-semibold flex items-center gap-1"
                            >
                                <i class="ph" :class="copiedKey === 'backend_env' ? 'ph-check' : 'ph-copy'"></i>
                                <span x-text="copiedKey === 'backend_env' ? '{{ __('Copied!') }}' : '{{ __('Copy Backend .env') }}'"></span>
                            </button>
                        </div>
                        <p class="text-[11px] text-body">
                            {{ __('WaPro automatically authenticates your storefront using the database-stored keys above. If you also want them pinned in marketing-app/.env, copy below:') }}
                        </p>
                        <pre class="rounded-xl bg-neutral-950 p-3 font-mono text-[11px] text-neutral-300 overflow-x-auto">{{ $backendEnvContent }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB: Live Shopping, Facebook Simulcast & Public Storage Videos --}}
    @php
        $liveCfg = (array) ($liveSettings ?? []);
        $fbCfg = (array) ($liveCfg['facebook'] ?? []);
        $fbReadiness = (array) ($fbCfg['readiness'] ?? ['ready' => false, 'status' => 'unconfigured', 'message' => 'Not configured']);
        $defaultPlatforms = (array) ($liveCfg['default_platforms'] ?? ['storefront', 'facebook']);
        $allLiveStreams = $liveStreams ?? collect();
    @endphp
    <div x-show="activeTab === 'live'" x-cloak class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Left Column: Live Settings + Facebook Live Config --}}
            <div class="lg:col-span-7 space-y-6">
                <form method="POST" action="{{ route('user.commerce.storefront.live.settings') }}" class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-6">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_tab" value="live">

                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400">
                                <i class="ph ph-broadcast text-xl"></i>
                            </span>
                            <div>
                                <h2 class="heading-4 text-title text-base font-semibold">{{ __('Live Shopping, WebRTC & Slider Settings') }}</h2>
                                <p class="text-xs text-body">{{ __('Control mobile-to-storefront WebRTC streaming, Laravel Reverb real-time chat, and public storage video sliders.') }}</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ ($liveCfg['enabled'] ?? true) ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400' }}">
                            <span class="size-2 rounded-full {{ ($liveCfg['enabled'] ?? true) ? 'bg-emerald-500' : 'bg-neutral-400' }}"></span>
                            {{ ($liveCfg['enabled'] ?? true) ? __('Live System Active') : __('Disabled') }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="flex items-start gap-3 rounded-xl border border-neutral-200 p-3.5 cursor-pointer hover:border-primary/40 dark:border-neutral-800">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" name="enabled" value="1" class="form-checkbox mt-0.5 rounded text-primary" @checked($liveCfg['enabled'] ?? true)>
                            <span class="space-y-0.5">
                                <span class="block text-xs font-semibold text-title">{{ __('Enable Live Shopping & Video Hub') }}</span>
                                <span class="block text-[11px] text-body">{{ __('Activates /live page on Storefront and Customer Mobile App.') }}</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-3 rounded-xl border border-neutral-200 p-3.5 cursor-pointer hover:border-primary/40 dark:border-neutral-800">
                            <input type="hidden" name="show_header_live_badge" value="0">
                            <input type="checkbox" name="show_header_live_badge" value="1" class="form-checkbox mt-0.5 rounded text-primary" @checked($liveCfg['show_header_live_badge'] ?? true)>
                            <span class="space-y-0.5">
                                <span class="block text-xs font-semibold text-title">{{ __('Show Header "LIVE NOW" Indicator') }}</span>
                                <span class="block text-[11px] text-body">{{ __('Displays a pulsing live badge in Storefront navigation during broadcasts.') }}</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-3 rounded-xl border border-neutral-200 p-3.5 cursor-pointer hover:border-primary/40 dark:border-neutral-800">
                            <input type="hidden" name="auto_save_recording" value="0">
                            <input type="checkbox" name="auto_save_recording" value="1" class="form-checkbox mt-0.5 rounded text-primary" @checked($liveCfg['auto_save_recording'] ?? true)>
                            <span class="space-y-0.5">
                                <span class="block text-xs font-semibold text-title">{{ __('Save Full Live to Public Storage') }}</span>
                                <span class="block text-[11px] text-body">{{ __('Stores recorded MP4 files in public/storage/commerce/lives for VOD & sliders.') }}</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-3 rounded-xl border border-neutral-200 p-3.5 cursor-pointer hover:border-primary/40 dark:border-neutral-800">
                            <input type="hidden" name="auto_publish_to_slider" value="0">
                            <input type="checkbox" name="auto_publish_to_slider" value="1" class="form-checkbox mt-0.5 rounded text-primary" @checked($liveCfg['auto_publish_to_slider'] ?? false)>
                            <span class="space-y-0.5">
                                <span class="block text-xs font-semibold text-title">{{ __('Auto-Add Recorded Video to Hero Slider') }}</span>
                                <span class="block text-[11px] text-body">{{ __('Automatically promotes completed live recordings into the Storefront Hero Slider.') }}</span>
                            </span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="form-label text-xs font-semibold">{{ __('Default Broadcast Targets (Owner Mobile App)') }}</label>
                            <div class="mt-1.5 flex flex-wrap gap-3">
                                <label class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-xs font-medium dark:border-neutral-700">
                                    <input type="checkbox" name="default_platforms[]" value="storefront" class="form-checkbox rounded text-primary" @checked(in_array('storefront', $defaultPlatforms, true))>
                                    <span>{{ __('Storefront & Customer App') }}</span>
                                </label>
                                <label class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-xs font-medium dark:border-neutral-700">
                                    <input type="checkbox" name="default_platforms[]" value="facebook" class="form-checkbox rounded text-primary" @checked(in_array('facebook', $defaultPlatforms, true))>
                                    <span>{{ __('Facebook Live (Simulcast)') }}</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label for="live_default_slider_id" class="form-label text-xs font-semibold">{{ __('Target Hero Slider for Saved Videos') }}</label>
                            <select id="live_default_slider_id" name="default_slider_id" class="form-select mt-1 w-full rounded-xl text-xs">
                                <option value="">{{ __('First Available Storefront Hero Slider') }}</option>
                                @foreach($sliders as $sl)
                                    <option value="{{ $sl->id }}" @selected(($liveCfg['default_slider_id'] ?? null) == $sl->id)>{{ $sl->name }} (#{{ $sl->id }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                        <div>
                            <label for="live_stun_url" class="form-label text-xs font-semibold">{{ __('WebRTC STUN Server URL') }}</label>
                            <input id="live_stun_url" type="text" name="stun_url" value="{{ $liveCfg['stun_url'] ?? 'stun:stun.l.google.com:19302' }}" class="form-input mt-1 w-full rounded-xl text-xs font-mono" placeholder="stun:stun.l.google.com:19302">
                        </div>
                        <div>
                            <label for="live_turn_url" class="form-label text-xs font-semibold">{{ __('Optional TURN Relay Server URL') }}</label>
                            <input id="live_turn_url" type="text" name="turn_url" value="{{ $liveCfg['turn_url'] ?? '' }}" class="form-input mt-1 w-full rounded-xl text-xs font-mono" placeholder="turn:turn.example.com:3478">
                        </div>
                        <div>
                            <label for="live_turn_username" class="form-label text-xs font-semibold">{{ __('TURN Username') }}</label>
                            <input id="live_turn_username" type="text" name="turn_username" value="{{ $liveCfg['turn_username'] ?? '' }}" class="form-input mt-1 w-full rounded-xl text-xs">
                        </div>
                        <div>
                            <label for="live_turn_credential" class="form-label text-xs font-semibold">{{ __('TURN Credential / Password') }}</label>
                            <input id="live_turn_credential" type="password" name="turn_credential" value="{{ $liveCfg['turn_credential'] ?? '' }}" class="form-input mt-1 w-full rounded-xl text-xs">
                        </div>
                    </div>

                    {{-- Facebook Live Simultaneous Streaming Configuration --}}
                    <div class="rounded-xl border border-blue-200/70 bg-blue-50/40 p-4 space-y-4 dark:border-blue-900/50 dark:bg-blue-950/20">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <i class="ph ph-facebook-logo text-xl text-blue-600 dark:text-blue-400"></i>
                                <div>
                                    <h3 class="text-xs font-bold text-title">{{ __('Facebook Live Simultaneous Broadcast (Simulcast)') }}</h3>
                                    <p class="text-[11px] text-body">{{ __('Stream to Facebook Live and Storefront simultaneously from a single mobile camera when Facebook settings are up to date.') }}</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ ($fbReadiness['ready'] ?? false) ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                <span class="size-1.5 rounded-full {{ ($fbReadiness['ready'] ?? false) ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                {{ ($fbReadiness['ready'] ?? false) ? __('Ready: ') . ($fbReadiness['page_name'] ?? 'Connected') : ($fbReadiness['message'] ?? __('Needs Setup')) }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="flex items-center gap-2.5 text-xs font-medium text-title cursor-pointer">
                                <input type="hidden" name="facebook[enabled]" value="0">
                                <input type="checkbox" name="facebook[enabled]" value="1" class="form-checkbox rounded text-blue-600" @checked($fbCfg['enabled'] ?? false)>
                                <span>{{ __('Enable Facebook Live Integration') }}</span>
                            </label>

                            <label class="flex items-center gap-2.5 text-xs font-medium text-title cursor-pointer">
                                <input type="hidden" name="facebook[use_connected_messenger_page]" value="0">
                                <input type="checkbox" name="facebook[use_connected_messenger_page]" value="1" class="form-checkbox rounded text-blue-600" @checked($fbCfg['use_connected_messenger_page'] ?? true)>
                                <span>{{ __('Use Connected Messenger Facebook Page Automatically') }}</span>
                            </label>

                            <label class="flex items-center gap-2.5 text-xs font-medium text-title cursor-pointer">
                                <input type="hidden" name="facebook[sync_facebook_comments]" value="0">
                                <input type="checkbox" name="facebook[sync_facebook_comments]" value="1" class="form-checkbox rounded text-blue-600" @checked($fbCfg['sync_facebook_comments'] ?? true)>
                                <span>{{ __('Sync Facebook Live Comments into Unified Reverb Chat') }}</span>
                            </label>

                            <label class="flex items-center gap-2.5 text-xs font-medium text-title cursor-pointer">
                                <input type="hidden" name="allow_guest_chat" value="0">
                                <input type="checkbox" name="allow_guest_chat" value="1" class="form-checkbox rounded text-primary" @checked($liveCfg['allow_guest_chat'] ?? true)>
                                <span>{{ __('Allow Guest Viewers to Comment on Storefront Live') }}</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label for="fb_page_id" class="form-label text-[11px] font-semibold">{{ __('Facebook Page ID') }}</label>
                                <input id="fb_page_id" type="text" name="facebook[page_id]" value="{{ $fbCfg['page_id'] ?? '' }}" placeholder="1029384756..." class="form-input mt-1 w-full rounded-lg text-xs font-mono">
                            </div>
                            <div>
                                <label for="fb_page_name" class="form-label text-[11px] font-semibold">{{ __('Facebook Page Name') }}</label>
                                <input id="fb_page_name" type="text" name="facebook[page_name]" value="{{ $fbCfg['page_name'] ?? '' }}" placeholder="My Brand Page" class="form-input mt-1 w-full rounded-lg text-xs">
                            </div>
                            <div>
                                <label for="fb_page_token" class="form-label text-[11px] font-semibold">{{ __('Page Access Token (publish_video)') }}</label>
                                <input id="fb_page_token" type="password" name="facebook[page_access_token]" value="" placeholder="{{ ($fbCfg['has_access_token'] ?? false) ? '•••••••••••••••• (Saved)' : 'EAAB...' }}" class="form-input mt-1 w-full rounded-lg text-xs font-mono">
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <span class="text-xs text-body">
                            {{ __('Reverb App Key:') }} <code class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-[11px] dark:bg-neutral-800">{{ $liveCfg['reverb']['app_key'] ?? 'fashionfiest-live-key' }}</code>
                        </span>
                        <button type="submit" class="btn btn-primary btn-sm rounded-xl px-5 py-2.5 text-xs font-semibold flex items-center gap-2">
                            <i class="ph ph-floppy-disk text-base"></i>
                            <span>{{ __('Save Live & Facebook Settings') }}</span>
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('user.commerce.storefront.live.verify-facebook') }}" class="flex items-center justify-between rounded-2xl border border-neutral-200 bg-white px-5 py-4 shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
                    @csrf
                    <input type="hidden" name="_tab" value="live">
                    <div class="space-y-0.5">
                        <p class="text-xs font-semibold text-title">{{ __('Test & Verify Facebook Live Connection') }}</p>
                        <p class="text-[11px] text-body">{{ __('Checks with Meta Graph API that your Page Access Token is valid and up to date before the owner goes live from the mobile app.') }}</p>
                    </div>
                    <button type="submit" class="btn btn-outline btn-sm rounded-xl px-4 py-2 text-xs font-semibold flex items-center gap-1.5 shrink-0">
                        <i class="ph ph-check-circle text-base"></i>
                        <span>{{ __('Verify Facebook Now') }}</span>
                    </button>
                </form>
            </div>

            {{-- Right Column: Upload / Schedule Live Stream or Video --}}
            <div class="lg:col-span-5">
                <form method="POST" action="{{ route('user.commerce.storefront.lives.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-4">
                    @csrf
                    <input type="hidden" name="_tab" value="live">

                    <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary/10 text-primary">
                            <i class="ph ph-video-camera text-lg"></i>
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-title">{{ __('Schedule Live or Upload Recorded Video') }}</h3>
                            <p class="text-xs text-body">{{ __('Videos uploaded here or recorded from the Mobile App are saved in public storage.') }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="new_live_title" class="form-label text-xs font-semibold">{{ __('Stream / Video Title') }}</label>
                        <input id="new_live_title" type="text" name="title" required maxlength="200" placeholder="Winter Heavyweight Hoodie Drop Live" class="form-input mt-1 w-full rounded-xl text-xs">
                    </div>

                    <div>
                        <label for="new_live_description" class="form-label text-xs font-semibold">{{ __('Description') }}</label>
                        <textarea id="new_live_description" name="description" rows="2" placeholder="Exclusive live showcase and Q&A..." class="form-textarea mt-1 w-full rounded-xl text-xs"></textarea>
                    </div>

                    <div>
                        <span class="form-label text-xs font-semibold block">{{ __('Broadcast Platforms') }}</span>
                        <div class="mt-1.5 flex flex-wrap gap-3">
                            <label class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-medium dark:border-neutral-700">
                                <input type="checkbox" name="broadcast_platforms[]" value="storefront" class="form-checkbox rounded text-primary" checked>
                                <span>{{ __('Storefront & App') }}</span>
                            </label>
                            <label class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-medium dark:border-neutral-700">
                                <input type="checkbox" name="broadcast_platforms[]" value="facebook" class="form-checkbox rounded text-primary" @checked($fbReadiness['ready'] ?? false)>
                                <span>{{ __('Facebook Live') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="new_live_thumbnail" class="form-label text-xs font-semibold">{{ __('Cover Poster Image') }}</label>
                            <input id="new_live_thumbnail" type="file" name="thumbnail" accept="image/*" class="form-input mt-1 w-full rounded-xl text-xs">
                        </div>
                        <div>
                            <label for="new_live_video" class="form-label text-xs font-semibold">{{ __('Recorded Video (.mp4 / Public Storage)') }}</label>
                            <input id="new_live_video" type="file" name="video" accept="video/mp4,video/webm,video/quicktime" class="form-input mt-1 w-full rounded-xl text-xs">
                        </div>
                    </div>

                    @if(isset($products) && $products->count())
                        <div>
                            <label for="new_live_products" class="form-label text-xs font-semibold">{{ __('Featured Catalog Products (Shop in Live)') }}</label>
                            <select id="new_live_products" name="product_ids[]" multiple class="form-select mt-1 w-full rounded-xl text-xs h-28">
                                @foreach($products as $prod)
                                    <option value="{{ $prod->id }}">{{ $prod->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-body">{{ __('Hold Cmd/Ctrl to select multiple products customers can buy during the stream.') }}</p>
                        </div>
                    @endif

                    <label class="flex items-center gap-2.5 rounded-xl border border-neutral-200 p-3 text-xs font-medium text-title cursor-pointer dark:border-neutral-800">
                        <input type="hidden" name="add_to_slider" value="0">
                        <input type="checkbox" name="add_to_slider" value="1" class="form-checkbox rounded text-primary" @checked($liveCfg['auto_publish_to_slider'] ?? false)>
                        <span>{{ __('Promote video directly into Storefront Hero Slider') }}</span>
                    </label>

                    <button type="submit" class="btn btn-primary btn-sm w-full rounded-xl py-2.5 text-xs font-semibold flex items-center justify-center gap-2">
                        <i class="ph ph-plus-circle text-base"></i>
                        <span>{{ __('Save Live Stream / Video') }}</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Live Streams & Public Storage Recordings Table --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-title">{{ __('All Live Broadcasts & Public Storage Recordings') }}</h3>
                    <p class="text-xs text-body">{{ __('All live sessions started from the Mobile App or uploaded here are saved in database and public storage. Toggle any recorded video into your Homepage Hero Slider.') }}</p>
                </div>
                @if($storeUrl)
                    <a href="{{ rtrim($storeUrl, '/') }}/live" target="_blank" rel="noopener" class="btn btn-outline btn-sm rounded-xl px-4 py-2 text-xs font-semibold flex items-center gap-1.5">
                        <i class="ph ph-arrow-square-out text-base"></i>
                        <span>{{ __('View Storefront /live Page') }}</span>
                    </a>
                @endif
            </div>

            @if($allLiveStreams->isEmpty())
                <div class="rounded-xl border border-dashed border-neutral-200 py-12 text-center dark:border-neutral-800">
                    <i class="ph ph-video-camera text-3xl text-neutral-400"></i>
                    <p class="mt-2 text-sm font-semibold text-title">{{ __('No live broadcasts or recorded videos yet') }}</p>
                    <p class="mt-1 text-xs text-body">{{ __('Go live from the Store Owner Mobile App or upload a video above to populate your Storefront Live Hub and Hero Slider.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-neutral-200 text-[11px] font-semibold uppercase text-neutral-500 dark:border-neutral-800">
                                <th class="pb-3 pr-4">{{ __('Stream / Video') }}</th>
                                <th class="pb-3 px-3">{{ __('Status & Platforms') }}</th>
                                <th class="pb-3 px-3">{{ __('Public Storage Video') }}</th>
                                <th class="pb-3 px-3">{{ __('Products & Views') }}</th>
                                <th class="pb-3 px-3">{{ __('Hero Slider') }}</th>
                                <th class="pb-3 pl-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @foreach($allLiveStreams as $stream)
                                <tr>
                                    <td class="py-3.5 pr-4">
                                        <div class="font-semibold text-title">{{ $stream->title }}</div>
                                        <div class="text-[11px] text-body">/live/{{ $stream->slug }}</div>
                                        @if($stream->started_at)
                                            <div class="text-[11px] text-neutral-400">{{ $stream->started_at->format('M d, Y H:i') }}</div>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-3 space-y-1">
                                        <div>
                                            @if($stream->status === 'live')
                                                <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                                    <span class="size-1.5 rounded-full bg-rose-500 animate-ping"></span>
                                                    {{ __('LIVE NOW') }}
                                                </span>
                                            @elseif($stream->status === 'ended')
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                                    {{ __('Recorded (VOD)') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                    {{ ucfirst($stream->status) }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach(($stream->broadcast_platforms ?? ['storefront']) as $plat)
                                                <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                                    {{ ucfirst($plat) }}
                                                </span>
                                            @endforeach
                                            @if($stream->facebook_permalink_url)
                                                <a href="{{ $stream->facebook_permalink_url }}" target="_blank" rel="noopener" class="text-[10px] font-semibold text-blue-600 hover:underline">
                                                    {{ __('FB Link') }}
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-3">
                                        @if($stream->videoUrl())
                                            <a href="{{ $stream->videoUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-primary font-semibold hover:underline">
                                                <i class="ph ph-play-circle text-base"></i>
                                                <span>{{ __('Play MP4') }}</span>
                                            </a>
                                            <div class="text-[10px] text-neutral-400 font-mono truncate max-w-44">{{ $stream->video_path }}</div>
                                        @else
                                            <span class="text-neutral-400">{{ __('No recording file yet') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-3">
                                        <div>{{ $stream->products->count() }} {{ __('products') }}</div>
                                        <div class="text-[11px] text-body">{{ $stream->total_views }} {{ __('views') }} · {{ $stream->likes_count }} {{ __('likes') }}</div>
                                    </td>
                                    <td class="py-3.5 px-3">
                                        <form method="POST" action="{{ route('user.commerce.storefront.lives.toggle-slider', $stream->id) }}">
                                            @csrf
                                            <input type="hidden" name="_tab" value="live">
                                            <input type="hidden" name="enable" value="{{ $stream->is_in_slider ? '0' : '1' }}">
                                            <button type="submit" class="btn btn-sm rounded-lg px-3 py-1.5 text-[11px] font-semibold {{ $stream->is_in_slider ? 'bg-primary text-white' : 'btn-outline' }}">
                                                <i class="ph {{ $stream->is_in_slider ? 'ph-check-circle' : 'ph-slideshow' }} mr-1"></i>
                                                {{ $stream->is_in_slider ? __('In Hero Slider') : __('Add to Slider') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="py-3.5 pl-3 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            @if($stream->status === 'live')
                                                <form method="POST" action="{{ route('user.commerce.storefront.lives.stop', $stream->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="_tab" value="live">
                                                    <button type="submit" class="btn btn-outline btn-sm rounded-lg px-2.5 py-1 text-[11px] text-amber-600 border-amber-300">
                                                        {{ __('End Live') }}
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('user.commerce.storefront.lives.destroy', $stream->id) }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="_tab" value="live">
                                                <button type="submit" class="btn btn-outline btn-sm rounded-lg px-2.5 py-1 text-[11px] text-rose-600 border-rose-200 hover:bg-rose-50">
                                                    <i class="ph ph-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
</x-layouts.user>
