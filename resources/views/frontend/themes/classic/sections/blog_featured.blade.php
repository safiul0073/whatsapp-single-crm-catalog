@php
    $d = $section->data ?? [];
    $posts = app(\App\Modules\Blogs\Services\BlogsService::class)->featuredPosts(2);
    $primaryPost = $posts->first();
    $secondaryPost = $posts->skip(1)->first();
    $primaryImageUrl = $primaryPost?->featuredImageUrl();
@endphp

@if ($primaryPost)
    <section class="blog-section" aria-labelledby="blog-featured-heading">
        <div class="section-container">
            <div class="blog-section-heading">
                <span>{{ $d['eyebrow_text'] ?? __('Featured') }}</span>
                <h2 id="blog-featured-heading">{{ $d['heading'] ?? __('Start with the latest playbook') }}</h2>
            </div>

            <div class="blog-featured">
                <article class="blog-featured__primary">
                    @if ($primaryImageUrl)
                        <a href="{{ route('blog.show', $primaryPost) }}" class="blog-featured__image" aria-label="{{ $primaryPost->title }}">
                            <img src="{{ $primaryImageUrl }}" alt="{{ $primaryPost->title }}">
                        </a>
                    @endif
                    <div class="blog-featured__content">
                        <div class="blog-card__meta">
                            <span>{{ optional($primaryPost->published_at)->format('M d, Y') }}</span>
                            <span>{{ trans_choice(':count min read|:count min read', $primaryPost->read_time_minutes, ['count' => $primaryPost->read_time_minutes]) }}</span>
                        </div>
                        <h3><a href="{{ route('blog.show', $primaryPost) }}">{{ $primaryPost->title }}</a></h3>
                        <p>{{ $primaryPost->excerpt }}</p>
                        <a href="{{ route('blog.show', $primaryPost) }}" class="blog-card__link">
                            {{ __('Read featured article') }}
                            <i data-lucide="arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </article>

                @if ($secondaryPost)
                    @include('frontend.themes.classic.sections.partials.blog-card', ['post' => $secondaryPost])
                @endif
            </div>
        </div>
    </section>
@endif
