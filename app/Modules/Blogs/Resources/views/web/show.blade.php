@extends($layoutView)

@php
    $description = $blog->seoDescription();
    $canonicalUrl = route('blog.show', $blog);
    $featuredImageUrl = $blog->featuredImageUrl();
    $imageUrl = $featuredImageUrl ?: asset('assets/brand/favicon.png');
    $contentHtml = $blog->safeContentHtml();
    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $blog->seoTitle(),
        'description' => $description,
        'image' => $imageUrl,
        'datePublished' => optional($blog->published_at)->toAtomString(),
        'dateModified' => optional($blog->updated_at)->toAtomString(),
        'author' => [
            '@type' => 'Person',
            'name' => $blog->author_name,
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('app.name'),
        ],
        'mainEntityOfPage' => $canonicalUrl,
    ];
@endphp

@section('title', $blog->seoTitle())
@section('meta_description', $description)

@push('head')
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $blog->seoTitle() }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $imageUrl }}">
    <meta property="article:published_time" content="{{ optional($blog->published_at)->toAtomString() }}">
    <meta property="article:modified_time" content="{{ optional($blog->updated_at)->toAtomString() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $blog->seoTitle() }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $imageUrl }}">
    <script type="application/ld+json"><?php echo json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
@endpush

@section('main')
    <article class="blog-detail" aria-labelledby="blog-detail-heading">
        <header class="blog-detail__hero">
            <div class="section-container">
                <div class="blog-detail__eyebrow">
                    <a href="{{ route('blog.index') }}">{{ __('Blog') }}</a>
                    <span>{{ optional($blog->published_at)->format('M d, Y') }}</span>
                    <span>{{ trans_choice(':count min read|:count min read', $blog->read_time_minutes, ['count' => $blog->read_time_minutes]) }}</span>
                </div>
                <h1 id="blog-detail-heading" class="blog-detail__title">{{ $blog->title }}</h1>
                @if ($blog->excerpt)
                    <p class="blog-detail__excerpt">{{ $blog->excerpt }}</p>
                @endif
                <p class="blog-detail__author">{{ __('By :author', ['author' => $blog->author_name]) }}</p>
            </div>
        </header>

        @if ($featuredImageUrl)
            <div class="section-container">
                <img src="{{ $featuredImageUrl }}" alt="{{ $blog->title }}" class="blog-detail__image">
            </div>
        @endif

        <div class="section-container">
            <div class="blog-detail__content">
                @if ($contentHtml)
                    <?php echo $contentHtml; ?>
                @else
                    <p>{{ $blog->excerpt }}</p>
                @endif
            </div>
        </div>
    </article>

    @if ($relatedPosts->isNotEmpty())
        <section class="blog-related" aria-labelledby="blog-related-heading">
            <div class="section-container">
                <div class="blog-section-heading">
                    <span>{{ __('Keep reading') }}</span>
                    <h2 id="blog-related-heading">{{ __('More WhatsApp growth ideas') }}</h2>
                </div>
                <div class="blog-card-grid">
                    @foreach ($relatedPosts as $post)
                        @include('frontend.themes.classic.sections.partials.blog-card', ['post' => $post])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
