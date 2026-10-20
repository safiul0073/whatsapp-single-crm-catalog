{{-- =====================================================================
     TAB 8: JOURNAL, FAQS & CURATED CONTENT
     ===================================================================== --}}
<div
    x-show="activeTab === 'content'"
    x-cloak
    class="space-y-6"
    x-data="{
        contentSubTab: 'journal',
        isCreatingBlog: false,
        editingBlog: null,
        isCreatingFaq: false,
        editingFaq: null,
        blogForm: {
            id: null,
            title: '',
            slug: '',
            category: 'Fabric & Quality',
            excerpt: '',
            content: '',
            cover_image: '',
            published: true,
        },
        faqForm: {
            id: null,
            question: '',
            slug: '',
            category: 'Orders & Shipping',
            answer: '',
            published: true,
        },
        openNewBlog() {
            this.editingBlog = null;
            this.blogForm = {
                id: null,
                title: '',
                slug: '',
                category: 'Fabric & Quality',
                excerpt: '',
                content: '',
                cover_image: '',
                published: true,
            };
            this.isCreatingBlog = true;
        },
        editBlog(item) {
            this.editingBlog = item.id;
            this.blogForm = {
                id: item.id,
                title: item.data?.title || '',
                slug: item.slug || '',
                category: item.data?.category || 'Fabric & Quality',
                excerpt: item.data?.excerpt || '',
                content: item.data?.content || '',
                cover_image: item.data?.cover_image || '',
                published: !!item.published_at,
            };
            this.isCreatingBlog = true;
            this.$nextTick(() => {
                document.getElementById('blog-form-card')?.scrollIntoView({ behavior: 'smooth' });
            });
        },
        autoSlugBlog() {
            if (!this.blogForm.title) return;
            this.blogForm.slug = this.blogForm.title
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        },
        openNewFaq() {
            this.editingFaq = null;
            this.faqForm = {
                id: null,
                question: '',
                slug: '',
                category: 'Orders & Shipping',
                answer: '',
                published: true,
            };
            this.isCreatingFaq = true;
        },
        editFaq(item) {
            this.editingFaq = item.id;
            this.faqForm = {
                id: item.id,
                question: item.data?.question || '',
                slug: item.slug || '',
                category: item.data?.category || 'Orders & Shipping',
                answer: item.data?.answer || '',
                published: !!item.published_at,
            };
            this.isCreatingFaq = true;
            this.$nextTick(() => {
                document.getElementById('faq-form-card')?.scrollIntoView({ behavior: 'smooth' });
            });
        },
        autoSlugFaq() {
            if (!this.faqForm.question) return;
            this.faqForm.slug = 'faq-' + this.faqForm.question
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .substring(0, 50);
        }
    }"
>
    {{-- Sub-tab navigation --}}
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-neutral-200 dark:border-neutral-800 pb-2">
        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="contentSubTab = 'journal'"
                :class="contentSubTab === 'journal'
                    ? 'bg-primary text-white shadow-xs'
                    : 'bg-white text-neutral-600 hover:bg-neutral-100 dark:bg-neutral-800 dark:text-neutral-300'"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all"
            >
                <i class="ph ph-article text-base"></i>
                <span>{{ __('The Journal (Blog Posts)') }}</span>
                <span class="rounded-full bg-white/20 px-2 py-0.5 text-[10px]">
                    {{ $blogs->count() }}
                </span>
            </button>

            <button
                type="button"
                @click="contentSubTab = 'faqs'"
                :class="contentSubTab === 'faqs'
                    ? 'bg-primary text-white shadow-xs'
                    : 'bg-white text-neutral-600 hover:bg-neutral-100 dark:bg-neutral-800 dark:text-neutral-300'"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all"
            >
                <i class="ph ph-question text-base"></i>
                <span>{{ __('FAQs') }}</span>
                <span class="rounded-full bg-white/20 px-2 py-0.5 text-[10px]">
                    {{ $faqs->count() }}
                </span>
            </button>

            <button
                type="button"
                @click="contentSubTab = 'collections'"
                :class="contentSubTab === 'collections'
                    ? 'bg-primary text-white shadow-xs'
                    : 'bg-white text-neutral-600 hover:bg-neutral-100 dark:bg-neutral-800 dark:text-neutral-300'"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all"
            >
                <i class="ph ph-folders text-base"></i>
                <span>{{ __('Curated Collections') }}</span>
                <span class="rounded-full bg-white/20 px-2 py-0.5 text-[10px]">
                    {{ $collections->count() }}
                </span>
            </button>
        </div>

        <div>
            <template x-if="contentSubTab === 'journal'">
                <button
                    type="button"
                    @click="openNewBlog()"
                    class="btn btn-primary btn-sm rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-xs"
                >
                    <i class="ph ph-plus text-sm"></i>
                    <span>{{ __('Write New Article') }}</span>
                </button>
            </template>
            <template x-if="contentSubTab === 'faqs'">
                <button
                    type="button"
                    @click="openNewFaq()"
                    class="btn btn-primary btn-sm rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-xs"
                >
                    <i class="ph ph-plus text-sm"></i>
                    <span>{{ __('Add FAQ Question') }}</span>
                </button>
            </template>
        </div>
    </div>

    {{-- =====================================================================
         SUB-TAB 1: THE JOURNAL (BLOG ARTICLES)
         ===================================================================== --}}
    <div x-show="contentSubTab === 'journal'" class="space-y-6">
        {{-- Editor Form Card --}}
        <div
            id="blog-form-card"
            x-show="isCreatingBlog"
            x-transition
            class="rounded-2xl border-2 border-primary/30 bg-white p-6 shadow-md dark:border-primary/40 dark:bg-neutral-900 space-y-5"
        >
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                        <i class="ph ph-pencil-simple text-lg"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-neutral-900 dark:text-white" x-text="blogForm.id ? '{{ __('Edit Journal Article') }}' : '{{ __('Write New Journal Article') }}'"></h3>
                        <p class="text-xs text-neutral-500">{{ __('Articles are published to your storefront at /blog/{slug}') }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="isCreatingBlog = false; editingBlog = null"
                    class="text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200"
                >
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>

            <form
                method="POST"
                :action="blogForm.id ? '{{ url('user/commerce/storefront/contents') }}/' + blogForm.id : '{{ route('user.commerce.storefront.contents.store') }}'"
                class="space-y-4"
            >
                @csrf
                <template x-if="blogForm.id">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <input type="hidden" name="_tab" value="content">
                <input type="hidden" name="type" value="blog">

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
                    {{-- Article Title --}}
                    <div class="sm:col-span-8">
                        <label class="form-label text-xs font-semibold">
                            {{ __('Article Title') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="title"
                            x-model="blogForm.title"
                            @input="if (!blogForm.id) autoSlugBlog()"
                            required
                            placeholder="e.g. The Ultimate Guide to Heavyweight 240+ GSM Streetwear"
                            class="form-input text-sm"
                        >
                    </div>

                    {{-- Category --}}
                    <div class="sm:col-span-4">
                        <label class="form-label text-xs font-semibold">
                            {{ __('Category') }}
                        </label>
                        <input
                            type="text"
                            name="category"
                            x-model="blogForm.category"
                            placeholder="e.g. Fabric & Quality"
                            class="form-input text-sm"
                            list="blog-category-presets"
                        >
                        <datalist id="blog-category-presets">
                            <option value="Fabric & Quality"></option>
                            <option value="Wholesale & Business"></option>
                            <option value="Care Guide"></option>
                            <option value="Streetwear Drops"></option>
                            <option value="Behind the Scenes"></option>
                        </datalist>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-12 items-end">
                    {{-- URL Slug --}}
                    <div class="sm:col-span-8">
                        <label class="form-label text-xs font-semibold">
                            {{ __('URL Slug') }} <span class="text-red-500">*</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                name="slug"
                                x-model="blogForm.slug"
                                required
                                pattern="^[a-z0-9][a-z0-9-]*$"
                                placeholder="guide-to-heavyweight-streetwear"
                                class="form-input text-xs font-mono"
                            >
                            <button
                                type="button"
                                @click="autoSlugBlog()"
                                class="btn btn-outline btn-sm rounded-lg text-xs shrink-0"
                                title="{{ __('Generate slug from title') }}"
                            >
                                <i class="ph ph-sparkle text-amber-500"></i>
                                <span>{{ __('Auto') }}</span>
                            </button>
                        </div>
                    </div>

                    {{-- Published Toggle --}}
                    <div class="sm:col-span-4 pb-2">
                        <input type="hidden" name="published" value="0">
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-neutral-800 dark:text-neutral-200">
                            <input
                                type="checkbox"
                                name="published"
                                value="1"
                                x-model="blogForm.published"
                                class="rounded text-primary focus:ring-primary size-4"
                            >
                            <span>{{ __('Publish Immediately') }}</span>
                        </label>
                    </div>
                </div>

                {{-- Excerpt --}}
                <div>
                    <label class="form-label text-xs font-semibold">
                        {{ __('Article Summary / Excerpt') }} <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        name="excerpt"
                        x-model="blogForm.excerpt"
                        required
                        rows="2"
                        placeholder="A concise 2-3 sentence overview of this article to display on cards and search results..."
                        class="form-input text-xs"
                    ></textarea>
                </div>

                {{-- Full Body Content --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="form-label text-xs font-semibold mb-0">
                            {{ __('Article Body Content') }} <span class="text-red-500">*</span>
                        </label>
                        <span class="text-[11px] text-neutral-400">
                            {{ __('Supports HTML tags: <h2>, <h3>, <p>, <ul>, <li>, <blockquote>, <strong>') }}
                        </span>
                    </div>
                    <textarea
                        name="content"
                        x-model="blogForm.content"
                        required
                        rows="8"
                        placeholder="Write your article text here. You can include paragraphs, headings, bullet lists, and styling guides..."
                        class="form-input text-xs font-mono leading-relaxed"
                    ></textarea>
                </div>

                {{-- Cover Image --}}
                <div>
                    <label class="form-label text-xs font-semibold">
                        {{ __('Cover Image URL / Storage Path (Optional)') }}
                    </label>
                    <input
                        type="text"
                        name="cover_image"
                        x-model="blogForm.cover_image"
                        placeholder="e.g. /storage/categories/men.jpg or https://..."
                        class="form-input text-xs font-mono"
                    >
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                    <button
                        type="button"
                        @click="isCreatingBlog = false; editingBlog = null"
                        class="btn btn-outline btn-sm rounded-xl text-xs"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary btn-sm rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm"
                    >
                        <i class="ph ph-check text-sm"></i>
                        <span x-text="blogForm.id ? '{{ __('Update Article') }}' : '{{ __('Publish Article') }}'"></span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Existing Articles List --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-4 dark:border-neutral-800">
                <div>
                    <h2 class="heading-4 text-title text-base font-semibold">{{ __('Published Journal Articles') }}</h2>
                    <p class="text-xs text-body">{{ __('Manage active articles and styling guides visible to storefront visitors.') }}</p>
                </div>
                <span class="text-xs font-mono text-neutral-400">
                    {{ $blogs->count() }} {{ __('articles') }}
                </span>
            </div>

            <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse($blogs as $blog)
                    <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 group">
                        <div class="space-y-1.5 flex-1 max-w-2xl">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-blue-50 text-brand dark:bg-blue-950/40">
                                    {{ $blog->data['category'] ?? 'Journal' }}
                                </span>
                                @if($blog->published_at)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                        {{ __('Published') }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">
                                        {{ __('Draft') }}
                                    </span>
                                @endif
                                <span class="text-[11px] text-neutral-400">
                                    {{ $blog->published_at ? $blog->published_at->format('M d, Y') : $blog->created_at->format('M d, Y') }}
                                </span>
                            </div>

                            <h3 class="text-sm font-bold text-neutral-900 dark:text-white leading-snug">
                                {{ $blog->data['title'] ?? 'Untitled Article' }}
                            </h3>

                            <p class="text-xs text-neutral-500 line-clamp-2">
                                {{ $blog->data['excerpt'] ?? '' }}
                            </p>

                            <div class="flex items-center gap-2 text-[11px] text-neutral-400 font-mono">
                                <span>/blog/{{ $blog->slug }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                type="button"
                                @click="editBlog({{ Illuminate\Support\Js::from($blog) }})"
                                class="btn btn-outline btn-sm rounded-lg text-xs font-semibold flex items-center gap-1"
                            >
                                <i class="ph ph-pencil-simple text-sm"></i>
                                <span>{{ __('Edit') }}</span>
                            </button>

                            <form
                                method="POST"
                                action="{{ route('user.commerce.storefront.contents.destroy', $blog->id) }}"
                                onsubmit="return confirm('{{ __('Are you sure you want to delete this article?') }}');"
                                class="inline-block"
                            >
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="_tab" value="content">
                                <button
                                    type="submit"
                                    class="btn btn-outline btn-sm rounded-lg text-xs text-red-600 hover:bg-red-50 hover:border-red-200 dark:hover:bg-red-950/40"
                                    title="{{ __('Delete article') }}"
                                >
                                    <i class="ph ph-trash text-sm"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-xs text-neutral-500 dark:border-neutral-700 space-y-2">
                        <span class="grid h-10 w-10 place-items-center rounded-full bg-blue-50 text-brand mx-auto dark:bg-blue-950/40">
                            <i class="ph ph-article text-xl"></i>
                        </span>
                        <p class="font-semibold text-neutral-800 dark:text-neutral-200">{{ __('No articles published yet.') }}</p>
                        <p>{{ __('Click "Write New Article" above to create your first journal post.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- =====================================================================
         SUB-TAB 2: FREQUENTLY ASKED QUESTIONS (FAQS)
         ===================================================================== --}}
    <div x-show="contentSubTab === 'faqs'" class="space-y-6">
        {{-- FAQ Editor Card --}}
        <div
            id="faq-form-card"
            x-show="isCreatingFaq"
            x-transition
            class="rounded-2xl border-2 border-primary/30 bg-white p-6 shadow-md dark:border-primary/40 dark:bg-neutral-900 space-y-5"
        >
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                        <i class="ph ph-question text-lg"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-neutral-900 dark:text-white" x-text="faqForm.id ? '{{ __('Edit FAQ Question') }}' : '{{ __('Add New FAQ Question') }}'"></h3>
                        <p class="text-xs text-neutral-500">{{ __('Answers are published live on your storefront /faqs page.') }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="isCreatingFaq = false; editingFaq = null"
                    class="text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200"
                >
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>

            <form
                method="POST"
                :action="faqForm.id ? '{{ url('user/commerce/storefront/contents') }}/' + faqForm.id : '{{ route('user.commerce.storefront.contents.store') }}'"
                class="space-y-4"
            >
                @csrf
                <template x-if="faqForm.id">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <input type="hidden" name="_tab" value="content">
                <input type="hidden" name="type" value="faq">

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
                    {{-- Question --}}
                    <div class="sm:col-span-8">
                        <label class="form-label text-xs font-semibold">
                            {{ __('Question') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="question"
                            x-model="faqForm.question"
                            @input="if (!faqForm.id) autoSlugFaq()"
                            required
                            placeholder="e.g. How long does standard and express shipping take?"
                            class="form-input text-sm"
                        >
                    </div>

                    {{-- Topic Category --}}
                    <div class="sm:col-span-4">
                        <label class="form-label text-xs font-semibold">
                            {{ __('Topic Category') }}
                        </label>
                        <input
                            type="text"
                            name="category"
                            x-model="faqForm.category"
                            placeholder="Orders & Shipping"
                            class="form-input text-sm"
                            list="faq-category-presets"
                        >
                        <datalist id="faq-category-presets">
                            <option value="Orders & Shipping"></option>
                            <option value="Wholesale & Custom Packs"></option>
                            <option value="Quality & Sizing"></option>
                            <option value="Returns & Exchanges"></option>
                            <option value="Payments & Security"></option>
                        </datalist>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-12 items-end">
                    {{-- Slug --}}
                    <div class="sm:col-span-8">
                        <label class="form-label text-xs font-semibold">
                            {{ __('Identifier Slug') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="slug"
                            x-model="faqForm.slug"
                            required
                            pattern="^[a-z0-9][a-z0-9-]*$"
                            placeholder="faq-shipping-rates"
                            class="form-input text-xs font-mono"
                        >
                    </div>

                    {{-- Published Toggle --}}
                    <div class="sm:col-span-4 pb-2">
                        <input type="hidden" name="published" value="0">
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-neutral-800 dark:text-neutral-200">
                            <input
                                type="checkbox"
                                name="published"
                                value="1"
                                x-model="faqForm.published"
                                class="rounded text-primary focus:ring-primary size-4"
                            >
                            <span>{{ __('Published on Storefront') }}</span>
                        </label>
                    </div>
                </div>

                {{-- Answer --}}
                <div>
                    <label class="form-label text-xs font-semibold">
                        {{ __('Answer Body') }} <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        name="answer"
                        x-model="faqForm.answer"
                        required
                        rows="5"
                        placeholder="Provide a clear, detailed answer to this customer question. HTML tags like <p>, <strong>, and <a href='...'> are supported..."
                        class="form-input text-xs leading-relaxed"
                    ></textarea>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                    <button
                        type="button"
                        @click="isCreatingFaq = false; editingFaq = null"
                        class="btn btn-outline btn-sm rounded-xl text-xs"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary btn-sm rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm"
                    >
                        <i class="ph ph-check text-sm"></i>
                        <span x-text="faqForm.id ? '{{ __('Update FAQ') }}' : '{{ __('Save Question') }}'"></span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Existing FAQs List --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-4 dark:border-neutral-800">
                <div>
                    <h2 class="heading-4 text-title text-base font-semibold">{{ __('Storefront Help Center FAQs') }}</h2>
                    <p class="text-xs text-body">{{ __('Frequently asked questions displayed on your /faqs page.') }}</p>
                </div>
                <span class="text-xs font-mono text-neutral-400">
                    {{ $faqs->count() }} {{ __('questions') }}
                </span>
            </div>

            <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse($faqs as $faq)
                    <div class="py-4 flex flex-col sm:flex-row sm:items-start justify-between gap-4 group">
                        <div class="space-y-1.5 flex-1 max-w-2xl">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                    {{ $faq->data['category'] ?? 'General' }}
                                </span>
                                @if($faq->published_at)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                        {{ __('Published') }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">
                                        {{ __('Draft') }}
                                    </span>
                                @endif
                            </div>

                            <h3 class="text-sm font-bold text-neutral-900 dark:text-white leading-snug">
                                {{ $faq->data['question'] ?? 'Question' }}
                            </h3>

                            <div class="text-xs text-neutral-600 dark:text-neutral-400 line-clamp-2 leading-relaxed">
                                {!! strip_tags($faq->data['answer'] ?? '') !!}
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                type="button"
                                @click="editFaq({{ Illuminate\Support\Js::from($faq) }})"
                                class="btn btn-outline btn-sm rounded-lg text-xs font-semibold flex items-center gap-1"
                            >
                                <i class="ph ph-pencil-simple text-sm"></i>
                                <span>{{ __('Edit') }}</span>
                            </button>

                            <form
                                method="POST"
                                action="{{ route('user.commerce.storefront.contents.destroy', $faq->id) }}"
                                onsubmit="return confirm('{{ __('Delete this question?') }}');"
                                class="inline-block"
                            >
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="_tab" value="content">
                                <button
                                    type="submit"
                                    class="btn btn-outline btn-sm rounded-lg text-xs text-red-600 hover:bg-red-50 hover:border-red-200 dark:hover:bg-red-950/40"
                                    title="{{ __('Delete question') }}"
                                >
                                    <i class="ph ph-trash text-sm"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-xs text-neutral-500 dark:border-neutral-700 space-y-2">
                        <span class="grid h-10 w-10 place-items-center rounded-full bg-blue-50 text-brand mx-auto dark:bg-blue-950/40">
                            <i class="ph ph-question text-xl"></i>
                        </span>
                        <p class="font-semibold text-neutral-800 dark:text-neutral-200">{{ __('No FAQs added yet.') }}</p>
                        <p>{{ __('Click "Add FAQ Question" above to start helping your customers.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- =====================================================================
         SUB-TAB 3: CURATED COLLECTIONS
         ===================================================================== --}}
    <div x-show="contentSubTab === 'collections'" class="space-y-6">
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-6">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-4 dark:border-neutral-800">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                        <i class="ph ph-folders text-lg"></i>
                    </span>
                    <div>
                        <h2 class="heading-4 text-title text-base font-semibold">{{ __('Curated Product Collections') }}</h2>
                        <p class="text-xs text-body">{{ __('Create custom product group shortcuts like /collections/deals or /collections/featured.') }}</p>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                @foreach($collections->concat([null]) as $content)
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50/50 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-3">
                        <form
                            method="POST"
                            action="{{ $content ? route('user.commerce.storefront.contents.update', $content->id) : route('user.commerce.storefront.contents.store') }}"
                            class="space-y-3"
                        >
                            @csrf
                            @if($content) @method('PUT') @endif
                            <input type="hidden" name="_tab" value="content">
                            <input type="hidden" name="type" value="collection">

                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                <div class="sm:col-span-5">
                                    <label class="form-label text-xs">{{ __('Collection Title') }} *</label>
                                    <input name="title" required value="{{ $content?->data['title'] ?? '' }}" placeholder="e.g. Summer Essentials" class="form-input text-xs font-semibold">
                                </div>
                                <div class="sm:col-span-4">
                                    <label class="form-label text-xs">{{ __('URL Slug') }} *</label>
                                    <input name="slug" required value="{{ $content?->slug }}" placeholder="e.g. deals" class="form-input text-xs font-mono">
                                </div>
                                <div class="sm:col-span-3 pb-2 flex items-center gap-2">
                                    <input type="hidden" name="published" value="0">
                                    <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold">
                                        <input type="checkbox" name="published" value="1" @checked($content?->published_at) class="rounded text-primary focus:ring-primary">
                                        <span>{{ __('Published') }}</span>
                                    </label>
                                </div>
                            </div>

                            <div class="flex justify-end pt-1 gap-2">
                                @if($content)
                                    <button
                                        type="button"
                                        form="delete-collection-{{ $content->id }}"
                                        onclick="if(confirm('{{ __('Delete collection?') }}')) document.getElementById('delete-collection-{{ $content->id }}').submit();"
                                        class="btn btn-outline btn-sm text-xs font-semibold rounded-lg text-red-600"
                                    >
                                        {{ __('Delete') }}
                                    </button>
                                @endif
                                <button type="submit" class="btn btn-primary btn-sm text-xs font-semibold rounded-lg flex items-center gap-1.5 shadow-xs">
                                    <i class="ph ph-check text-sm"></i>
                                    <span>{{ $content ? __('Update Collection') : __('Create Collection') }}</span>
                                </button>
                            </div>
                        </form>

                        @if($content)
                            <form id="delete-collection-{{ $content->id }}" method="POST" action="{{ route('user.commerce.storefront.contents.destroy', $content->id) }}" class="hidden">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="_tab" value="content">
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
