@php
    $d = $section->data ?? [];
    $posts = app(\App\Modules\Blogs\Services\BlogsService::class)->featuredPosts(3);
@endphp

@if ($posts->isNotEmpty())
    <section class="blog-section blog-section--home" aria-labelledby="home-blog-heading">
        <div class="section-container">
            <div class="blog-section-heading blog-section-heading--split">
                <div>
                    <span>{{ $d['eyebrow_text'] ?? __('From the Blog') }}</span>
                    <h2 id="home-blog-heading">
                        {{ $d['heading_line_one'] ?? __('Insights &') }}
                        <strong>{{ $d['heading_highlight'] ?? __('articles') }}</strong>
                    </h2>
                    @if (!empty($d['subheading']))
                        <p>{{ $d['subheading'] }}</p>
                    @endif
                </div>
                <a href="{{ $d['cta_link'] ?? route('blog.index') }}" class="blog-section-heading__link">
                    {{ $d['cta_text'] ?? __('View all posts') }}
                    <i data-lucide="arrow-right" aria-hidden="true"></i>
                </a>
            </div>

            <div class="blog-card-grid">
                @foreach ($posts as $post)
                    @include('frontend.themes.classic.sections.partials.blog-card', ['post' => $post])
                @endforeach
            </div>
        </div>
    </section>
@endif
