<x-layouts.user :title="__('Videos')">
    @php
        $liveCfg = (array) ($liveSettings ?? []);
        $initialSource = in_array(old('video_source'), ['youtube', 'upload'], true) ? old('video_source') : 'youtube';
    @endphp

    <div class="mx-auto w-full max-w-7xl space-y-6 pb-12">
        @include('commerce::user.live.partials.header', [
            'active' => 'videos',
            'title' => __('Videos'),
            'subtitle' => __('Upload MP4/WebM/MOV videos or add YouTube links. Customers watch them directly in the Storefront Live & Videos player.'),
            'liveSettings' => $liveCfg,
            'storeUrl' => $storeUrl ?? null,
        ])

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-xs font-medium text-body">{{ __('Total Videos') }}</p>
                <p class="mt-1 text-2xl font-bold text-title">{{ number_format($stats['total'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-xs font-medium text-body">{{ __('YouTube Links') }}</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($stats['youtube'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-xs font-medium text-body">{{ __('Uploaded Videos') }}</p>
                <p class="mt-1 text-2xl font-bold text-primary">{{ number_format($stats['uploaded'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-xs font-medium text-body">{{ __('In Hero Slider') }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($stats['in_slider'] ?? 0) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Video Library Table --}}
            <div class="lg:col-span-7">
                <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-4">
                    <div>
                        <h2 class="text-base font-semibold text-title">{{ __('Storefront Video Library') }}</h2>
                        <p class="text-xs text-body">{{ __('Includes uploaded videos, YouTube links, and saved live stream replays. Toggle any video into your Homepage Hero Slider.') }}</p>
                    </div>

                    @if($videos->isEmpty())
                        <div class="rounded-xl border border-dashed border-neutral-200 py-12 text-center dark:border-neutral-800">
                            <i class="ph ph-video-camera text-3xl text-neutral-400"></i>
                            <p class="mt-2 text-sm font-semibold text-title">{{ __('No videos added yet') }}</p>
                            <p class="mt-1 text-xs text-body">{{ __('Upload a video file or paste a YouTube link using the form to publish it on your storefront.') }}</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-neutral-200 text-[11px] font-semibold uppercase text-neutral-500 dark:border-neutral-800">
                                        <th class="pb-3 pr-4">{{ __('Video') }}</th>
                                        <th class="pb-3 px-3">{{ __('Video Source') }}</th>
                                        <th class="pb-3 px-3">{{ __('Products & Views') }}</th>
                                        <th class="pb-3 px-3">{{ __('Hero Slider') }}</th>
                                        <th class="pb-3 pl-3 text-right">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                                    @foreach($videos as $video)
                                        <tr>
                                            <td class="py-3.5 pr-4">
                                                <div class="flex items-center gap-3">
                                                    @if($video->thumbnailUrl())
                                                        <img src="{{ $video->thumbnailUrl() }}" alt="{{ $video->title }}" class="h-10 w-16 rounded-lg object-cover bg-neutral-100 shrink-0 dark:bg-neutral-800">
                                                    @else
                                                        <span class="grid h-10 w-16 place-items-center rounded-lg bg-neutral-100 text-neutral-400 shrink-0 dark:bg-neutral-800">
                                                            <i class="ph ph-video-camera text-lg"></i>
                                                        </span>
                                                    @endif
                                                    <div class="min-w-0">
                                                        <div class="font-semibold text-title truncate max-w-48">{{ $video->title }}</div>
                                                        <div class="text-[11px] text-body">/live/{{ $video->slug }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3.5 px-3">
                                                @if($video->youtubeId())
                                                    <a href="{{ $video->videoUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-rose-600 font-semibold hover:underline">
                                                        <i class="ph ph-youtube-logo text-base"></i>
                                                        <span>{{ __('YouTube Video') }}</span>
                                                    </a>
                                                    <div class="text-[10px] text-neutral-400 font-mono truncate max-w-40">{{ $video->videoUrl() }}</div>
                                                @elseif($video->videoUrl())
                                                    <a href="{{ $video->videoUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-primary font-semibold hover:underline">
                                                        <i class="ph ph-play-circle text-base"></i>
                                                        <span>{{ __('Play MP4') }}</span>
                                                    </a>
                                                    <div class="text-[10px] text-neutral-400 font-mono truncate max-w-40">{{ $video->video_path }}</div>
                                                @else
                                                    <span class="text-neutral-400">{{ __('No video source') }}</span>
                                                @endif
                                            </td>
                                            <td class="py-3.5 px-3">
                                                <div>{{ $video->products->count() }} {{ __('products') }}</div>
                                                <div class="text-[11px] text-body">{{ $video->total_views }} {{ __('views') }} · {{ $video->likes_count }} {{ __('likes') }}</div>
                                            </td>
                                            <td class="py-3.5 px-3">
                                                <form method="POST" action="{{ route('user.commerce.storefront.lives.toggle-slider', $video->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="_return" value="videos">
                                                    <input type="hidden" name="enable" value="{{ $video->is_in_slider ? '0' : '1' }}">
                                                    <button type="submit" class="btn btn-sm rounded-lg px-3 py-1.5 text-[11px] font-semibold {{ $video->is_in_slider ? 'bg-primary text-white' : 'btn-outline' }}">
                                                        <i class="ph {{ $video->is_in_slider ? 'ph-check-circle' : 'ph-slideshow' }} mr-1"></i>
                                                        {{ $video->is_in_slider ? __('In Hero Slider') : __('Add to Slider') }}
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="py-3.5 pl-3 text-right">
                                                <form method="POST" action="{{ route('user.commerce.storefront.lives.destroy', $video->id) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="_return" value="videos">
                                                    <button type="submit" class="btn btn-outline btn-sm rounded-lg px-2.5 py-1 text-[11px] text-rose-600 border-rose-200 hover:bg-rose-50" aria-label="{{ __('Delete video') }}">
                                                        <i class="ph ph-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Upload Video or YouTube Link Form --}}
            <div class="lg:col-span-5">
                <form
                    method="POST"
                    action="{{ route('user.commerce.storefront.lives.store') }}"
                    enctype="multipart/form-data"
                    x-data="{
                        source: {{ Illuminate\Support\Js::from($initialSource) }},
                        videoUrl: {{ Illuminate\Support\Js::from((string) old('video_url', '')) }},
                        videoName: '',
                        videoSize: '',
                        thumbName: '',
                        thumbPreview: '',
                        submitting: false,
                        get youtubeId() {
                            const url = (this.videoUrl || '').trim();
                            if (!url) return null;
                            const match = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|shorts\/|live\/|v\/|watch\?(?:[^#]*&)?v=)|youtube-nocookie\.com\/embed\/)([A-Za-z0-9_-]{11})(?:[?&#/]|$)/i);
                            return match ? match[1] : null;
                        },
                        switchSource(next) {
                            this.source = next;
                            if (next === 'youtube') {
                                this.clearVideo();
                            } else {
                                this.videoUrl = '';
                            }
                        },
                        onVideoChange(event) {
                            const file = event.target.files && event.target.files[0];
                            if (!file) {
                                this.videoName = '';
                                this.videoSize = '';
                                return;
                            }
                            this.videoName = file.name;
                            const mb = file.size / (1024 * 1024);
                            this.videoSize = mb >= 1 ? mb.toFixed(1) + ' MB' : Math.max(1, Math.round(file.size / 1024)) + ' KB';
                        },
                        clearVideo() {
                            this.videoName = '';
                            this.videoSize = '';
                            if (this.$refs.videoInput) this.$refs.videoInput.value = '';
                        },
                        onThumbChange(event) {
                            const file = event.target.files && event.target.files[0];
                            if (this.thumbPreview) URL.revokeObjectURL(this.thumbPreview);
                            if (!file) {
                                this.thumbName = '';
                                this.thumbPreview = '';
                                return;
                            }
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
                    <input type="hidden" name="_return" value="videos">
                    <input type="hidden" name="video_source" :value="source">
                    <input type="hidden" name="broadcast_platforms[]" value="storefront">

                    <div class="flex items-center gap-3 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-primary shrink-0">
                            <i class="ph ph-video-camera text-xl"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-title">{{ __('Upload Video or Add YouTube Link') }}</h2>
                            <p class="text-xs text-body">{{ __('Plays directly inside your Storefront Live & Videos player.') }}</p>
                        </div>
                    </div>

                    {{-- Video Source Switcher --}}
                    <div class="space-y-3">
                        <span class="form-label mb-0 text-xs font-semibold">{{ __('Video Source') }}</span>
                        <div class="grid grid-cols-2 gap-1.5 rounded-xl bg-neutral-100 p-1 dark:bg-neutral-800">
                            <button
                                type="button"
                                @click="switchSource('youtube')"
                                :class="source === 'youtube' ? 'bg-white text-title shadow-2xs dark:bg-neutral-900' : 'text-body hover:text-title'"
                                class="flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold transition cursor-pointer"
                            >
                                <i class="ph ph-youtube-logo text-base" :class="source === 'youtube' ? 'text-rose-600' : ''"></i>
                                <span>{{ __('YouTube Link') }}</span>
                            </button>
                            <button
                                type="button"
                                @click="switchSource('upload')"
                                :class="source === 'upload' ? 'bg-white text-title shadow-2xs dark:bg-neutral-900' : 'text-body hover:text-title'"
                                class="flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold transition cursor-pointer"
                            >
                                <i class="ph ph-upload-simple text-base" :class="source === 'upload' ? 'text-primary' : ''"></i>
                                <span>{{ __('Upload Video') }}</span>
                            </button>
                        </div>

                        {{-- YouTube Input + Instant Preview --}}
                        <div x-show="source === 'youtube'" class="space-y-2.5">
                            <div class="relative">
                                <i class="ph ph-youtube-logo pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-lg text-rose-500"></i>
                                <input
                                    id="new_video_url"
                                    type="url"
                                    name="video_url"
                                    maxlength="500"
                                    x-model="videoUrl"
                                    :disabled="source !== 'youtube'"
                                    placeholder="https://www.youtube.com/watch?v=... or https://youtu.be/..."
                                    class="form-input input-with-leading-icon rounded-xl py-2.5 text-xs"
                                >
                            </div>

                            <div
                                x-show="youtubeId"
                                x-cloak
                                class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/70 p-2.5 dark:border-emerald-900/60 dark:bg-emerald-950/30"
                            >
                                <img
                                    :src="youtubeId ? 'https://i.ytimg.com/vi/' + youtubeId + '/hqdefault.jpg' : ''"
                                    alt="{{ __('YouTube preview thumbnail') }}"
                                    class="h-12 w-20 rounded-lg object-cover bg-neutral-900 shrink-0"
                                >
                                <div class="min-w-0 flex-1">
                                    <p class="flex items-center gap-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                        <i class="ph-fill ph-check-circle text-sm"></i>
                                        <span>{{ __('Valid YouTube video detected') }}</span>
                                    </p>
                                    <p class="truncate text-[11px] text-body">{{ __('Thumbnail loads automatically unless you upload a custom cover.') }}</p>
                                </div>
                            </div>

                            <p
                                x-show="videoUrl.trim() !== '' && !youtubeId"
                                x-cloak
                                class="flex items-center gap-1.5 text-[11px] font-medium text-amber-700 dark:text-amber-300"
                            >
                                <i class="ph ph-warning-circle text-sm"></i>
                                <span>{{ __('Paste a valid YouTube link (watch, shorts, live, or youtu.be).') }}</span>
                            </p>
                        </div>

                        {{-- File Upload Dropzone --}}
                        <div x-show="source === 'upload'" x-cloak>
                            <input
                                id="new_video_file"
                                x-ref="videoInput"
                                type="file"
                                name="video"
                                accept="video/mp4,video/webm,video/quicktime"
                                :disabled="source !== 'upload'"
                                @change="onVideoChange($event)"
                                class="sr-only"
                            >

                            <label
                                x-show="!videoName"
                                for="new_video_file"
                                class="flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-neutral-200 bg-neutral-50/70 px-4 py-6 text-center transition hover:border-primary/50 hover:bg-primary/5 dark:border-neutral-700 dark:bg-neutral-800/40"
                            >
                                <span class="grid h-10 w-10 place-items-center rounded-full bg-white text-primary shadow-2xs dark:bg-neutral-900">
                                    <i class="ph ph-film-strip text-xl"></i>
                                </span>
                                <span class="text-xs font-semibold text-title">{{ __('Click to choose a video file') }}</span>
                                <span class="text-[11px] text-body">{{ __('MP4, WebM, or MOV · up to 500 MB') }}</span>
                            </label>

                            <div
                                x-show="videoName"
                                x-cloak
                                class="flex items-center justify-between gap-3 rounded-xl border border-primary/30 bg-primary/5 p-3"
                            >
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-primary/10 text-primary shrink-0">
                                        <i class="ph ph-file-video text-lg"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-title" x-text="videoName"></p>
                                        <p class="text-[11px] text-body" x-text="videoSize"></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <label for="new_video_file" class="cursor-pointer rounded-lg border border-neutral-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-title hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                                        {{ __('Change') }}
                                    </label>
                                    <button type="button" @click="clearVideo()" class="cursor-pointer rounded-lg p-1 text-neutral-500 hover:text-rose-600" aria-label="{{ __('Remove video') }}">
                                        <i class="ph ph-x text-sm"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Title & Description --}}
                    <div class="space-y-4 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                        <div>
                            <label for="new_video_title" class="form-label text-xs font-semibold">
                                {{ __('Video Title') }} <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="new_video_title"
                                type="text"
                                name="title"
                                required
                                maxlength="200"
                                value="{{ old('title') }}"
                                placeholder="Spring Collection Lookbook"
                                class="form-input rounded-xl py-2.5 text-xs"
                            >
                        </div>

                        <div>
                            <label for="new_video_description" class="form-label text-xs font-semibold">
                                {{ __('Description') }} <span class="font-normal text-neutral-400">({{ __('Optional') }})</span>
                            </label>
                            <textarea
                                id="new_video_description"
                                name="description"
                                rows="3"
                                maxlength="5000"
                                placeholder="Highlight fabrics, sizing, and featured styles..."
                                class="form-input rounded-xl py-2.5 text-xs"
                            >{{ old('description') }}</textarea>
                        </div>
                    </div>

                    {{-- Cover Poster Image --}}
                    <div class="space-y-1.5">
                        <label for="new_video_thumbnail" class="form-label mb-0 text-xs font-semibold">
                            {{ __('Cover Poster Image') }} <span class="font-normal text-neutral-400">({{ __('Optional') }})</span>
                        </label>
                        <input
                            id="new_video_thumbnail"
                            x-ref="thumbInput"
                            type="file"
                            name="thumbnail"
                            accept="image/*"
                            @change="onThumbChange($event)"
                            class="sr-only"
                        >
                        <div class="flex items-center gap-3 rounded-xl border border-neutral-200 bg-neutral-50/50 p-2.5 dark:border-neutral-700 dark:bg-neutral-800/40">
                            <template x-if="thumbPreview">
                                <img :src="thumbPreview" alt="{{ __('Selected cover preview') }}" class="h-12 w-20 rounded-lg object-cover bg-neutral-100 shrink-0">
                            </template>
                            <template x-if="!thumbPreview && source === 'youtube' && youtubeId">
                                <img :src="'https://i.ytimg.com/vi/' + youtubeId + '/hqdefault.jpg'" alt="{{ __('YouTube cover preview') }}" class="h-12 w-20 rounded-lg object-cover bg-neutral-900 shrink-0 opacity-85">
                            </template>
                            <template x-if="!thumbPreview && !(source === 'youtube' && youtubeId)">
                                <span class="grid h-12 w-20 place-items-center rounded-lg bg-white text-neutral-400 border border-neutral-200 shrink-0 dark:border-neutral-700 dark:bg-neutral-900">
                                    <i class="ph ph-image text-xl"></i>
                                </span>
                            </template>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-medium text-title" x-text="thumbName || (source === 'youtube' ? '{{ __('Using YouTube thumbnail automatically') }}' : '{{ __('No cover image selected') }}')"></p>
                                <p class="text-[11px] text-body">{{ __('JPG, PNG, or WebP · 16:9 recommended') }}</p>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0">
                                <label for="new_video_thumbnail" class="cursor-pointer rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-title transition hover:border-primary/40 hover:text-primary dark:border-neutral-700 dark:bg-neutral-900">
                                    <span x-text="thumbName ? '{{ __('Change') }}' : '{{ __('Choose') }}'"></span>
                                </label>
                                <button
                                    type="button"
                                    x-show="thumbName"
                                    x-cloak
                                    @click="clearThumb()"
                                    class="cursor-pointer rounded-lg p-1.5 text-neutral-500 hover:text-rose-600"
                                    aria-label="{{ __('Remove cover image') }}"
                                >
                                    <i class="ph ph-x text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Searchable Product Picker --}}
                    @include('commerce::user.live.partials.product-picker', [
                        'id' => 'video_products',
                        'label' => __('Featured Catalog Products (Shop in Video)'),
                        'help' => __('Customers can tap and add these products to cart while watching.'),
                        'products' => $products ?? collect(),
                    ])

                    {{-- Hero Slider Toggle --}}
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-neutral-200 bg-neutral-50/50 p-3.5 transition hover:border-primary/40 dark:border-neutral-800 dark:bg-neutral-800/30">
                        <input type="hidden" name="add_to_slider" value="0">
                        <input type="checkbox" name="add_to_slider" value="1" class="form-checkbox mt-0.5 rounded text-primary" @checked($liveCfg['auto_publish_to_slider'] ?? false)>
                        <span class="space-y-0.5">
                            <span class="block text-xs font-semibold text-title">{{ __('Promote to Storefront Hero Slider') }}</span>
                            <span class="block text-[11px] text-body">{{ __('Also feature this video at the top of your storefront homepage.') }}</span>
                        </span>
                    </label>

                    <button
                        type="submit"
                        :disabled="submitting"
                        class="btn btn-primary btn-sm w-full rounded-xl py-3 text-xs font-semibold flex items-center justify-center gap-2 disabled:opacity-60"
                    >
                        <i class="ph text-base" :class="submitting ? 'ph-spinner animate-spin' : 'ph-plus-circle'"></i>
                        <span x-text="submitting ? '{{ __('Publishing…') }}' : '{{ __('Publish Video') }}'"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.user>
