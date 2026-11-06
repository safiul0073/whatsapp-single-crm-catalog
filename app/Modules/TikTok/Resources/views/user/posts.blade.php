@use('App\Modules\TikTok\Models\TikTokPost')
<x-layouts.user :title="__('TikTok posts')">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="heading-2">{{ __('TikTok posts') }}</h2>
            <p class="m-text mt-1">{{ __('Post videos to TikTok, or send them to your TikTok drafts to finish in the app.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('user.tiktok.index') }}" class="btn-sm btn-outline"><i class="ph ph-tiktok-logo text-base"></i> {{ __('TikTok accounts') }}</a>
            @if ($accounts->isNotEmpty())
                <a href="{{ route('user.tiktok.posts.create') }}" class="btn-sm btn-primary" data-tiktok-post-create><i class="ph ph-plus text-base"></i> {{ __('New TikTok video') }}</a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <x-ui.alert type="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($accounts->isEmpty())
        <section class="section-card mt-6" data-tiktok-posts-empty>
            <h3 class="heading-5">{{ __('Connect a TikTok account first') }}</h3>
            <p class="mt-1 text-sm text-body">{{ __('Connect TikTok and allow video posting to publish from here.') }}</p>
            <a href="{{ route('user.tiktok.index') }}" class="btn btn-primary mt-4">{{ __('Connect TikTok') }}</a>
        </section>
    @endif

    <section class="section-card mt-6">
        @if ($posts->isEmpty())
            <p class="text-sm text-body" data-tiktok-posts-none>{{ __('No TikTok videos sent yet.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr>
                            <th class="py-2 pr-3">{{ __('Video') }}</th>
                            <th class="py-2 pr-3">{{ __('Account') }}</th>
                            <th class="py-2 pr-3">{{ __('How') }}</th>
                            <th class="py-2 pr-3">{{ __('Status') }}</th>
                            <th class="py-2 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($posts as $post)
                            <tr class="border-t border-neutral-200 align-top" data-tiktok-post="{{ $post->id }}">
                                <td class="py-2 pr-3">
                                    <p class="text-heading">{{ $post->title ? str($post->title)->limit(100) : __('(no caption)') }}</p>
                                    <p class="text-xs text-body">{{ $post->created_at->toDayDateTimeString() }}</p>
                                    @if ($post->fail_reason)
                                        <p class="mt-1 text-xs text-error">{{ $post->fail_reason }}</p>
                                    @endif
                                    @if ($post->status === 'in_inbox')
                                        <p class="mt-1 text-xs text-body">{{ __('Open the TikTok app notification to finish and post it.') }}</p>
                                    @endif
                                </td>
                                <td class="py-2 pr-3 text-body">{{ $post->channelAccount?->name }}</td>
                                <td class="py-2 pr-3 text-body">{{ $post->mode === 'direct' ? __('Posted directly').' · '.TikTokPost::privacyLabel((string) $post->privacy_level) : __('Draft in TikTok inbox') }}</td>
                                <td class="py-2 pr-3"><span class="badge {{ $post->statusBadge() }}">{{ $post->statusLabel() }}</span></td>
                                <td class="py-2">
                                    <div class="flex justify-end gap-2">
                                        @if ($post->status === 'processing')
                                            <form method="POST" action="{{ route('user.tiktok.posts.refresh', $post) }}">
                                                @csrf
                                                <button type="submit" class="btn-sm btn-outline">{{ __('Check status') }}</button>
                                            </form>
                                        @endif
                                        @if (! $post->isPending())
                                            <form method="POST" action="{{ route('user.tiktok.posts.destroy', $post) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-sm btn-outline">{{ __('Remove') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.user>
