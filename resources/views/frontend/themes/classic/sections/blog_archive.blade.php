@php
    $d = $section->data ?? [];
    $posts = app(\App\Modules\Blogs\Services\BlogsService::class)->publicPosts(9);
@endphp

<section class="blog-section" aria-labelledby="blog-archive-heading">
    <div class="section-container">
        <div class="blog-section-heading">
            <span>{{ $d['eyebrow_text'] ?? __('Archive') }}</span>
            <h2 id="blog-archive-heading">{{ $d['heading'] ?? __('All articles') }}</h2>
            @if (!empty($d['subheading']))
                <p>{{ $d['subheading'] }}</p>
            @endif
        </div>

        @if ($posts->isNotEmpty())
            <div class="blog-card-grid">
                @foreach ($posts as $post)
                    @include('frontend.themes.classic.sections.partials.blog-card', ['post' => $post])
                @endforeach
            </div>

            @if ($posts->hasPages())
                <div class="blog-pagination">
                    {{ $posts->links() }}
                </div>
            @endif
        @else
            <p class="blog-empty">{{ __('No blog posts are published yet.') }}</p>
        @endif
    </div>
</section>
