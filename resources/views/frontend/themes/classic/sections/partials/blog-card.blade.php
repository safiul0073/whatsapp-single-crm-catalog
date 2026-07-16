<article class="blog-card">
    @php($featuredImageUrl = $post->featuredImageUrl())
    @if ($featuredImageUrl)
        <a href="{{ route('blog.show', $post) }}" class="blog-card__media" aria-label="{{ $post->title }}">
            <img src="{{ $featuredImageUrl }}" alt="{{ $post->title }}">
        </a>
    @endif
    <div class="blog-card__body">
        <div class="blog-card__meta">
            <span>{{ optional($post->published_at)->format('M d, Y') }}</span>
            <span>{{ trans_choice(':count min read|:count min read', $post->read_time_minutes, ['count' => $post->read_time_minutes]) }}</span>
        </div>
        <h3>
            <a href="{{ route('blog.show', $post) }}">{{ $post->title }}</a>
        </h3>
        @if ($post->excerpt)
            <p>{{ $post->excerpt }}</p>
        @endif
        <a href="{{ route('blog.show', $post) }}" class="blog-card__link">
            {{ __('Read article') }}
            <i data-lucide="arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</article>
