<?php

namespace App\Modules\Blogs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Blogs\Models\Blog;
use App\Modules\Blogs\Services\BlogsService;
use App\Modules\Frontend\Models\Page;
use App\Modules\Frontend\Services\ActiveThemeResolver;
use App\Modules\Frontend\Services\FrontendPageService;
use App\Modules\Frontend\Services\PageRenderService;
use Illuminate\Contracts\View\View;

class BlogController extends Controller
{
    public function __construct(
        protected BlogsService $blogs,
        protected FrontendPageService $pages,
        protected ActiveThemeResolver $activeThemeResolver,
        protected PageRenderService $renderer
    ) {}

    public function show(Blog $blog): View
    {
        $blog = $this->blogs->publicPostBySlug($blog->slug);
        $page = $this->pages->findBySlug('blog') ?? new Page([
            'title' => 'Blog',
            'slug' => 'blog',
            'status' => 'published',
            'default_layout' => 'default',
            'meta_title' => 'Blog',
            'meta_description' => 'Latest articles and insights.',
        ]);

        $payload = $this->renderer->payload($page, $this->activeThemeResolver->resolve());
        $payload['blog'] = $blog;
        $payload['relatedPosts'] = Blog::query()
            ->active()
            ->published()
            ->whereKeyNot($blog->getKey())
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('blogs::web.show', $payload);
    }
}
