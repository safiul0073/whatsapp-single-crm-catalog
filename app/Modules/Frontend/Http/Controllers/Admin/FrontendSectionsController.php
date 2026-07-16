<?php

namespace App\Modules\Frontend\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Frontend\Models\FrontendSection;
use App\Modules\Frontend\Services\FrontendSectionService;
use App\Modules\Frontend\Services\SectionRegistry;
use App\Modules\Frontend\Services\ThemeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FrontendSectionsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:frontend-sections.view', only: ['index']),
            new Middleware('permission:frontend-sections.create', only: ['create', 'store']),
            new Middleware('permission:frontend-sections.edit', only: ['edit', 'update']),
            new Middleware('permission:frontend-sections.delete', only: ['destroy']),
        ];
    }

    public function __construct(
        protected FrontendSectionService $service,
        protected SectionRegistry $sections,
        protected ThemeRegistry $themes
    ) {}

    public function index(Request $request): View
    {
        $sections = $this->service->listPaginated([
            'search' => $request->get('search'),
            'type' => $request->get('type'),
            'status' => $request->get('status'),
        ]);

        $annotations = [];

        foreach ($sections as $section) {
            $annotations[$section->id] = $this->service->annotate($section);
        }

        $sectionTypes = $this->sections->options();
        $themeLabels = $this->themes->options();

        return view('frontend::admin.frontend-sections.index', compact('sections', 'annotations', 'sectionTypes', 'themeLabels'));
    }

    public function create(Request $request): View
    {
        $type = (string) $request->get('type', array_key_first($this->sections->all()));
        $definition = $this->sections->get($type);

        return view('frontend::admin.frontend-sections.create', [
            'sectionTypes' => $this->sections->options(),
            'selectedType' => $type,
            'definition' => $definition,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSection($request);
        $this->service->create($validated);

        return redirect()
            ->route('admin.frontend-sections.index')
            ->with('success', __('Frontend section created successfully.'));
    }

    public function edit(FrontendSection $frontendSection): View
    {
        $definition = $this->sections->get($frontendSection->type);

        return view('frontend::admin.frontend-sections.edit', [
            'section' => $frontendSection,
            'definition' => $definition,
            'themeLabels' => $this->themes->options(),
        ]);
    }

    public function update(Request $request, FrontendSection $frontendSection): RedirectResponse
    {
        $validated = $this->validateSection($request, $frontendSection);
        $this->service->update($frontendSection, $validated);

        return redirect()
            ->route('admin.frontend-sections.index')
            ->with('success', __('Frontend section updated successfully.'));
    }

    public function destroy(FrontendSection $frontendSection): RedirectResponse
    {
        $this->service->delete($frontendSection);

        return redirect()
            ->route('admin.frontend-sections.index')
            ->with('success', __('Frontend section deleted successfully.'));
    }

    protected function validateSection(Request $request, ?FrontendSection $section = null): array
    {
        $type = (string) $request->input('type');
        $definition = $this->sections->get($type);
        abort_if(! $definition, 404);

        $slug = $request->input('slug') ?: Str::slug((string) $request->input('name'));
        $rules = [
            'type' => ['required', Rule::in(array_keys($this->sections->all()))],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('frontend_sections', 'slug')->ignore($section?->id)],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'description' => ['nullable', 'string'],
            'preview_image_media_id' => ['nullable', 'integer'],
        ];

        foreach (($definition['fields'] ?? []) as $key => $field) {
            if (! empty($field['rules'])) {
                $rules["data.{$key}"] = $field['rules'];
            }
        }

        $validated = $request->validate($rules);
        $validated['slug'] = $slug;

        return $validated;
    }
}
