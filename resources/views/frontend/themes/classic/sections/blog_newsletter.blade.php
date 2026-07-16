@php
    $d = $section->data ?? [];
@endphp

<section class="blog-newsletter" aria-labelledby="blog-newsletter-heading">
    <div class="section-container">
        <div class="blog-newsletter__inner">
            <div>
                <span>{{ $d['eyebrow_text'] ?? __('Newsletter') }}</span>
                <h2 id="blog-newsletter-heading">{{ $d['heading'] ?? __('Get WhatsApp growth notes in your inbox') }}</h2>
                <p>{{ $d['subheading'] ?? __('Practical automation, CRM, and campaign ideas for teams that use messaging every day.') }}</p>
            </div>
            <form method="POST" action="{{ route('newsletter.subscribe') }}" class="blog-newsletter__form" data-blog-newsletter>
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <label for="blog-newsletter-email">{{ __('Email address') }}</label>
                <div>
                    <input id="blog-newsletter-email" type="email" name="email" placeholder="{{ __('you@example.com') }}" required>
                    <button type="submit" data-blog-submit>
                        <span data-blog-submit-text>{{ $d['button_text'] ?? __('Subscribe') }}</span>
                        <i data-lucide="send" data-blog-submit-icon aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
