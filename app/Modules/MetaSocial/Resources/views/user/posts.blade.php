<x-layouts.user :title="__('Page & Instagram posts')">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="heading-2">{{ __('Page & Instagram posts') }}</h2>
            <p class="m-text mt-1">{{ __('Write a post once and publish it to your Facebook Page and Instagram, now or at a time you choose.') }}</p>
        </div>
        @if ($context['pages'] !== [])
            <a href="{{ route('user.meta-social.posts.create') }}" class="btn-sm btn-primary" data-meta-post-create><i class="ph ph-plus text-base"></i> {{ __('New post') }}</a>
        @endif
    </div>

    @if (session('status'))
        <x-ui.alert type="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="error" class="mt-4">{{ session('error') }}</x-ui.alert>
    @endif
    @if (($errors ?? null)?->any())
        <x-ui.alert type="error" class="mt-4">{{ $errors->first() }}</x-ui.alert>
    @endif

    @if ($context['pages'] === [])
        <section class="section-card mt-6" data-meta-posts-empty>
            <h3 class="heading-5">{{ __('Connect a Facebook Page first') }}</h3>
            <p class="mt-1 text-sm text-body">{{ __('Connect your Meta Business (or a Messenger Page) to publish posts. Instagram posting uses the Instagram account linked to that Page.') }}</p>
            <a href="{{ route('user.meta-social.business.show') }}" class="btn btn-primary mt-4">{{ __('Connect Meta') }}</a>
        </section>
    @else
        <section class="section-card mt-6">
            <h3 class="heading-5">{{ __('Posts from this dashboard') }}</h3>
            @if ($context['posts']->isEmpty())
                <p class="mt-2 text-sm text-body" data-meta-posts-none>{{ __('No posts yet.') }}</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr>
                                <th class="py-2 pr-3">{{ __('Post') }}</th>
                                <th class="py-2 pr-3">{{ __('Where') }}</th>
                                <th class="py-2 pr-3">{{ __('When') }}</th>
                                <th class="py-2 pr-3">{{ __('Status') }}</th>
                                <th class="py-2 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($context['posts'] as $post)
                                <tr class="border-t border-neutral-200 align-top" data-meta-post="{{ $post->id }}">
                                    <td class="py-2 pr-3">
                                        <p class="text-heading">{{ str($post->message)->limit(120) }}</p>
                                        @foreach ((array) $post->errors as $target => $error)
                                            <p class="mt-1 text-xs text-error">{{ ucfirst($target) }}: {{ $error }}</p>
                                        @endforeach
                                    </td>
                                    <td class="py-2 pr-3">
                                        @foreach ($post->targetList() as $target)
                                            <span class="badge badge-light">{{ $target === 'facebook' ? __('Facebook') : __('Instagram') }}</span>
                                        @endforeach
                                    </td>
                                    <td class="py-2 pr-3 text-body">{{ ($post->published_at ?? $post->scheduled_at ?? $post->created_at)?->toDayDateTimeString() }}</td>
                                    <td class="py-2 pr-3"><span class="badge {{ $post->statusBadge() }}">{{ $post->statusLabel() }}</span></td>
                                    <td class="py-2">
                                        <div class="flex justify-end gap-2">
                                            @if ($post->facebookUrl())
                                                <a href="{{ $post->facebookUrl() }}" target="_blank" rel="noopener" class="btn-sm btn-outline">{{ __('View') }}</a>
                                            @endif
                                            @if (in_array($post->status, ['failed', 'partial'], true))
                                                <form method="POST" action="{{ route('user.meta-social.posts.retry', $post) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-sm btn-outline">{{ __('Try again') }}</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('user.meta-social.posts.destroy', $post) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="btn-sm btn-outline text-error hover:border-error hover:text-error"
                                                    data-confirm
                                                    data-confirm-title="{{ __('Delete this post?') }}"
                                                    data-confirm-message="{{ __('It is removed from your Facebook Page. Instagram posts must be deleted in the Instagram app.') }}"
                                                    data-confirm-button="{{ __('Delete') }}"
                                                    data-confirm-variant="danger"
                                                    data-confirm-icon="ph-trash"
                                                >{{ __('Delete') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="section-card mt-6">
            <form method="GET" action="{{ route('user.meta-social.posts.index') }}" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="recent" value="1">
                <div>
                    <label for="recent_page" class="form-label">{{ __('Facebook Page') }}</label>
                    <select id="recent_page" name="page_id" class="form-input">
                        @foreach ($context['pages'] as $page)
                            <option value="{{ $page['id'] }}" @selected($pageId === $page['id'])>{{ $page['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="recent_instagram" class="form-label">{{ __('Instagram') }}</label>
                    <select id="recent_instagram" name="instagram_account_id" class="form-input">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($context['instagram_accounts'] as $instagram)
                            <option value="{{ $instagram['id'] }}" @selected($instagramId === $instagram['id'])>{{ $instagram['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-sm btn-outline"><i class="ph ph-arrows-clockwise text-base"></i> {{ __('Show what is live now') }}</button>
            </form>

            @if ($recent)
                @if ($recent['error'])
                    <x-ui.alert type="error" class="mt-4">{{ $recent['error'] }}</x-ui.alert>
                @endif
                <div class="mt-4 grid gap-6 lg:grid-cols-2" data-meta-recent>
                    @foreach (['facebook' => __('On Facebook'), 'instagram' => __('On Instagram')] as $network => $title)
                        <div>
                            <h4 class="font-semibold text-heading">{{ $title }}</h4>
                            @forelse ($recent[$network] as $item)
                                <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="mt-2 flex gap-3 rounded-md border border-neutral-200 p-2 text-sm">
                                    @if ($item['picture'])
                                        <img src="{{ $item['picture'] }}" alt="" class="h-14 w-14 rounded object-cover">
                                    @endif
                                    <span class="text-body">{{ $item['message'] ?: __('(no text)') }}</span>
                                </a>
                            @empty
                                <p class="mt-2 text-sm text-body">{{ __('Nothing to show.') }}</p>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</x-layouts.user>
