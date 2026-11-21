<x-layouts.user :title="__('Live Streams')">
    @php
        $liveCfg = (array) ($liveSettings ?? []);
        $fbReadiness = (array) ($liveCfg['facebook']['readiness'] ?? ['ready' => false]);
    @endphp

    <div class="mx-auto w-full max-w-7xl space-y-6 pb-12">
        @include('commerce::user.live.partials.header', [
            'active' => 'live',
            'title' => __('Live Streams'),
            'subtitle' => __('Schedule live shopping broadcasts, monitor active streams from the mobile app, and manage featured products.'),
            'liveSettings' => $liveCfg,
            'storeUrl' => $storeUrl ?? null,
        ])

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-xs font-medium text-body">{{ __('Live Now') }}</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($stats['live_now'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-xs font-medium text-body">{{ __('Scheduled') }}</p>
                <p class="mt-1 text-2xl font-bold text-title">{{ number_format($stats['scheduled'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-xs font-medium text-body">{{ __('Total Views') }}</p>
                <p class="mt-1 text-2xl font-bold text-title">{{ number_format($stats['total_views'] ?? 0) }}</p>
            </div>
            <a href="{{ route('user.commerce.storefront.videos') }}" class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-2xs transition hover:border-primary/40 dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-xs font-medium text-body">{{ __('Saved Videos & YouTube') }}</p>
                <p class="mt-1 text-2xl font-bold text-primary">{{ number_format($stats['videos_count'] ?? 0) }}</p>
            </a>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Active & Scheduled Broadcasts --}}
            <div class="lg:col-span-7">
                <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-title">{{ __('Active & Scheduled Broadcasts') }}</h2>
                            <p class="text-xs text-body">{{ __('Go live from the Store Owner Mobile App or schedule upcoming sessions here. Completed broadcasts move to the Videos library.') }}</p>
                        </div>
                        <a href="{{ route('user.commerce.storefront.videos') }}" class="btn btn-outline btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold flex items-center gap-1.5">
                            <i class="ph ph-video-camera text-base"></i>
                            <span>{{ __('Open Videos') }}</span>
                        </a>
                    </div>

                    @if($streams->isEmpty())
                        <div class="rounded-xl border border-dashed border-neutral-200 py-12 text-center dark:border-neutral-800">
                            <i class="ph ph-broadcast text-3xl text-neutral-400"></i>
                            <p class="mt-2 text-sm font-semibold text-title">{{ __('No active or scheduled live streams') }}</p>
                            <p class="mt-1 text-xs text-body">{{ __('Schedule an upcoming broadcast on the right or go live directly from the Store Owner Mobile App.') }}</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-neutral-200 text-[11px] font-semibold uppercase text-neutral-500 dark:border-neutral-800">
                                        <th class="pb-3 pr-4">{{ __('Stream') }}</th>
                                        <th class="pb-3 px-3">{{ __('Status & Platforms') }}</th>
                                        <th class="pb-3 px-3">{{ __('Products & Views') }}</th>
                                        <th class="pb-3 pl-3 text-right">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                                    @foreach($streams as $stream)
                                        <tr>
                                            <td class="py-3.5 pr-4">
                                                <div class="font-semibold text-title">{{ $stream->title }}</div>
                                                <div class="text-[11px] text-body">/live/{{ $stream->slug }}</div>
                                                @if($stream->started_at)
                                                    <div class="text-[11px] text-neutral-400">{{ __('Started') }} {{ $stream->started_at->format('M d, Y H:i') }}</div>
                                                @elseif($stream->scheduled_at)
                                                    <div class="text-[11px] text-neutral-400">{{ __('Scheduled') }} {{ $stream->scheduled_at->format('M d, Y H:i') }}</div>
                                                @endif
                                            </td>
                                            <td class="py-3.5 px-3 space-y-1">
                                                <div>
                                                    @if($stream->status === 'live')
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                                            <span class="size-1.5 rounded-full bg-rose-500 animate-ping"></span>
                                                            {{ __('LIVE NOW') }}
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
                                                <div>{{ $stream->products->count() }} {{ __('products') }}</div>
                                                <div class="text-[11px] text-body">{{ $stream->total_views }} {{ __('views') }} · {{ $stream->likes_count }} {{ __('likes') }}</div>
                                            </td>
                                            <td class="py-3.5 pl-3 text-right">
                                                <div class="inline-flex items-center gap-2">
                                                    @if($stream->status === 'live')
                                                        <form method="POST" action="{{ route('user.commerce.storefront.lives.stop', $stream->id) }}">
                                                            @csrf
                                                            <input type="hidden" name="_return" value="live">
                                                            <button type="submit" class="btn btn-outline btn-sm rounded-lg px-2.5 py-1 text-[11px] text-amber-600 border-amber-300">
                                                                {{ __('End Live') }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                    <form method="POST" action="{{ route('user.commerce.storefront.lives.destroy', $stream->id) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="_return" value="live">
                                                        <button type="submit" class="btn btn-outline btn-sm rounded-lg px-2.5 py-1 text-[11px] text-rose-600 border-rose-200 hover:bg-rose-50" aria-label="{{ __('Delete live stream') }}">
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

            {{-- Schedule Live Stream Form --}}
            <div class="lg:col-span-5">
                <form
                    method="POST"
                    action="{{ route('user.commerce.storefront.lives.store') }}"
                    enctype="multipart/form-data"
                    x-data="{
                        thumbName: '',
                        thumbPreview: '',
                        submitting: false,
                        onThumbChange(event) {
                            const file = event.target.files && event.target.files[0];
                            if (this.thumbPreview) URL.revokeObjectURL(this.thumbPreview);
                            if (!file) { this.thumbName = ''; this.thumbPreview = ''; return; }
                            this.thumbName = file.name;
                            this.thumbPreview = URL.createObjectURL(file);
                        },
                        clearThumb() {
                            if (this.thumbPreview) URL.revokeObjectURL(this.thumbPreview);
                            this.thumbName = '';
                            this.thumbPreview = '';
                            if (this.$refs.thumbInput) this.$refs.thumbInput.value = '';
                        }
                    }"
                    @submit="submitting = true"
                    class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-5"
                >
                    @csrf
                    <input type="hidden" name="_return" value="live">

                    <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 shrink-0">
                            <i class="ph ph-broadcast text-xl"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-title">{{ __('Schedule Live Stream') }}</h2>
                            <p class="text-xs text-body">{{ __('Plan an upcoming live show for your Storefront and Customer Mobile App.') }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label for="new_live_title" class="form-label text-xs font-semibold">
                                {{ __('Stream Title') }} <span class="text-rose-500">*</span>
                            </label>
                            <input id="new_live_title" type="text" name="title" required maxlength="200" value="{{ old('title') }}" placeholder="Winter Heavyweight Hoodie Drop Live" class="form-input rounded-xl py-2.5 text-xs">
                        </div>

                        <div>
                            <label for="new_live_description" class="form-label text-xs font-semibold">
                                {{ __('Description') }} <span class="font-normal text-neutral-400">({{ __('Optional') }})</span>
                            </label>
                            <textarea id="new_live_description" name="description" rows="3" maxlength="5000" placeholder="Exclusive live showcase and Q&A..." class="form-input rounded-xl py-2.5 text-xs">{{ old('description') }}</textarea>
                        </div>

                        <div>
                            <label for="new_live_scheduled_at" class="form-label text-xs font-semibold">
                                {{ __('Schedule Date & Time') }} <span class="font-normal text-neutral-400">({{ __('Optional') }})</span>
                            </label>
                            <input id="new_live_scheduled_at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="form-input rounded-xl py-2.5 text-xs">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="new_live_thumbnail" class="form-label mb-0 text-xs font-semibold">
                            {{ __('Cover Poster Image') }} <span class="font-normal text-neutral-400">({{ __('Optional') }})</span>
                        </label>
                        <input id="new_live_thumbnail" x-ref="thumbInput" type="file" name="thumbnail" accept="image/*" @change="onThumbChange($event)" class="sr-only">
                        <div class="flex items-center gap-3 rounded-xl border border-neutral-200 bg-neutral-50/50 p-2.5 dark:border-neutral-700 dark:bg-neutral-800/40">
                            <template x-if="thumbPreview">
                                <img :src="thumbPreview" alt="{{ __('Cover preview') }}" class="h-12 w-20 rounded-lg object-cover bg-neutral-100 shrink-0">
                            </template>
                            <template x-if="!thumbPreview">
                                <span class="grid h-12 w-20 place-items-center rounded-lg bg-white text-neutral-400 border border-neutral-200 shrink-0 dark:border-neutral-700 dark:bg-neutral-900">
                                    <i class="ph ph-image text-xl"></i>
                                </span>
                            </template>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-medium text-title" x-text="thumbName || '{{ __('No cover image selected') }}'"></p>
                                <p class="text-[11px] text-body">{{ __('JPG, PNG, or WebP · 16:9 recommended') }}</p>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <label for="new_live_thumbnail" class="cursor-pointer rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-title transition hover:border-primary/40 hover:text-primary dark:border-neutral-700 dark:bg-neutral-900">
                                    <span x-text="thumbName ? '{{ __('Change') }}' : '{{ __('Choose') }}'"></span>
                                </label>
                                <button type="button" x-show="thumbName" x-cloak @click="clearThumb()" class="cursor-pointer rounded-lg p-1.5 text-neutral-500 hover:text-rose-600" aria-label="{{ __('Remove cover image') }}">
                                    <i class="ph ph-x text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <span class="form-label text-xs font-semibold block">{{ __('Broadcast Platforms') }}</span>
                        <div class="mt-1.5 grid grid-cols-2 gap-2">
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-neutral-200 px-3 py-2.5 text-xs font-medium transition hover:border-primary/40 dark:border-neutral-700">
                                <input type="checkbox" name="broadcast_platforms[]" value="storefront" class="form-checkbox rounded text-primary" checked>
                                <span>{{ __('Storefront & App') }}</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-neutral-200 px-3 py-2.5 text-xs font-medium transition hover:border-primary/40 dark:border-neutral-700">
                                <input type="checkbox" name="broadcast_platforms[]" value="facebook" class="form-checkbox rounded text-primary" @checked($fbReadiness['ready'] ?? false)>
                                <span>{{ __('Facebook Live') }}</span>
                            </label>
                        </div>
                    </div>

                    @include('commerce::user.live.partials.product-picker', [
                        'id' => 'live_products',
                        'label' => __('Featured Catalog Products (Shop in Live)'),
                        'help' => __('Customers can buy these products directly during the live stream.'),
                        'products' => $products ?? collect(),
                    ])

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-neutral-200 bg-neutral-50/50 p-3.5 transition hover:border-primary/40 dark:border-neutral-800 dark:bg-neutral-800/30">
                        <input type="hidden" name="add_to_slider" value="0">
                        <input type="checkbox" name="add_to_slider" value="1" class="form-checkbox mt-0.5 rounded text-primary" @checked($liveCfg['auto_publish_to_slider'] ?? false)>
                        <span class="space-y-0.5">
                            <span class="block text-xs font-semibold text-title">{{ __('Auto-promote recording to Hero Slider') }}</span>
                            <span class="block text-[11px] text-body">{{ __('Adds the saved replay to your homepage slider once the live ends.') }}</span>
                        </span>
                    </label>

                    <button
                        type="submit"
                        :disabled="submitting"
                        class="btn btn-primary btn-sm w-full rounded-xl py-3 text-xs font-semibold flex items-center justify-center gap-2 disabled:opacity-60"
                    >
                        <i class="ph text-base" :class="submitting ? 'ph-spinner animate-spin' : 'ph-calendar-plus'"></i>
                        <span x-text="submitting ? '{{ __('Scheduling…') }}' : '{{ __('Schedule Live Stream') }}'"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.user>
