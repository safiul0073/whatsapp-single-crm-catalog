<?php

namespace App\Modules\Frontend\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Frontend\Models\FrontendSection;
use App\Modules\Frontend\Models\Page;
use App\Modules\Frontend\Services\ActiveThemeResolver;
use App\Modules\Frontend\Services\FrontendPageService;
use App\Modules\Frontend\Services\PageComposerService;
use App\Modules\Frontend\Services\ThemeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FrontendPagesController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:frontend-pages.view', only: ['index']),
            new Middleware('permission:frontend-pages.create', only: ['create', 'store']),
            new Middleware('permission:frontend-pages.edit', only: ['edit', 'update']),
            new Middleware('permission:frontend-pages.delete', only: ['destroy']),
            new Middleware('permission:frontend-pages.publish', only: ['publish']),
        ];
    }

    public function __construct(
        protected FrontendPageService $service,
        protected PageComposerService $composer,
        protected ThemeRegistry $themes,
        protected ActiveThemeResolver $activeThemeResolver
    ) {}

    public function index(Request $request): View
    {
        $pages = $this->service->listPaginated([
            'search' => $request->get('search'),
            'status' => $request->get('status'),
        ]);

        $compatibleThemes = [];

        foreach ($pages as $page) {
            $compatibleThemes[$page->id] = $this->composer->compatibleThemes($page);
        }

        $themeLabels = $this->themes->options();

        return view('frontend::admin.frontend-pages.index', compact('pages', 'compatibleThemes', 'themeLabels'));
    }

    public function create(): View
    {
        return view('frontend::admin.frontend-pages.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePage($request);
        $page = $this->service->create($validated);

        return redirect()
            ->route('admin.frontend-pages.edit', $page)
            ->with('success', __('Frontend page created successfully.'));
    }

    public function edit(Page $frontendPage): View
    {
        $frontendPage->load(['sections', 'pageSections']);

        return view('frontend::admin.frontend-pages.edit', array_merge(
            ['page' => $frontendPage],
            $this->formData($frontendPage)
        ));
    }

    public function update(Request $request, Page $frontendPage): RedirectResponse
    {
        $validated = $this->validatePage($request, $frontendPage);
        $this->service->update($frontendPage, $validated);

        return redirect()
            ->route('admin.frontend-pages.edit', $frontendPage)
            ->with('success', __('Frontend page updated successfully.'));
    }

    public function destroy(Page $frontendPage): RedirectResponse
    {
        if (! $this->service->delete($frontendPage)) {
            return back()->with('error', __('System pages cannot be deleted.'));
        }

        return redirect()
            ->route('admin.frontend-pages.index')
            ->with('success', __('Frontend page deleted successfully.'));
    }

    public function publish(Page $frontendPage): RedirectResponse
    {
        $this->service->update($frontendPage, array_merge($frontendPage->toArray(), [
            'status' => $frontendPage->status === 'published' ? 'draft' : 'published',
            'sections' => $frontendPage->sections->pluck('id')->all(),
        ]));

        return back()->with('success', __('Page publishing state updated successfully.'));
    }

    protected function validatePage(Request $request, ?Page $page = null): array
    {
        $slug = $request->input('slug') ?: Str::slug((string) $request->input('title'));
        $reservedSlugs = ['admin', 'dashboard', 'login', 'register', 'forgot-password', 'reset-password', 'locale', 'email'];

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::notIn($reservedSlugs),
                Rule::unique('pages', 'slug')->ignore($page?->id),
            ],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'excerpt' => ['nullable', 'string'],
            'default_layout' => ['required', Rule::in(array_keys($this->themes->layoutOptions()))],
            'is_home' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_image_media_id' => ['nullable', 'integer'],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['integer', 'exists:frontend_sections,id'],
        ]);

        $validated['slug'] = $slug;

        return $validated;
    }

    protected function formData(?Page $page = null): array
    {
        $sections = FrontendSection::query()
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();

        $attachedSectionIds = $page?->sections->pluck('id')->all() ?? [];

        return [
            'sections' => $sections,
            'attachedSectionIds' => $attachedSectionIds,
            'layoutOptions' => $this->themes->layoutOptions(),
            'activeThemeLabel' => $this->themes->label($this->activeThemeResolver->resolve()),
        ];
    }
}
