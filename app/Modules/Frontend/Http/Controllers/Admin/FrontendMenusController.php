<?php

namespace App\Modules\Frontend\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Frontend\Models\FrontendMenu;
use App\Modules\Frontend\Models\Page;
use App\Modules\Frontend\Services\MenuAssignmentService;
use App\Modules\Frontend\Services\MenuService;
use App\Modules\Frontend\Services\MenuSlotRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FrontendMenusController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:frontend-menus.view', only: ['index']),
            new Middleware('permission:frontend-menus.create', only: ['create', 'store']),
            new Middleware('permission:frontend-menus.edit', only: ['edit', 'update']),
            new Middleware('permission:frontend-menus.delete', only: ['destroy']),
            new Middleware('permission:frontend-menus.publish', only: ['publish']),
        ];
    }

    public function __construct(
        protected MenuService $service,
        protected MenuAssignmentService $assignments,
        protected MenuSlotRegistry $slots
    ) {}

    public function index(Request $request): View
    {
        $menus = $this->service->listPaginated([
            'search' => $request->get('search'),
            'status' => $request->get('status'),
        ]);

        return view('frontend::admin.frontend-menus.index', [
            'menus' => $menus,
            'usageMap' => $this->assignments->usageMap($menus->getCollection()),
        ]);
    }

    public function create(): View
    {
        return view('frontend::admin.frontend-menus.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $menu = $this->service->create($this->validateMenu($request));

        return redirect()
            ->route('admin.frontend-menus.edit', $menu)
            ->with('success', __('Frontend menu created successfully.'));
    }

    public function edit(FrontendMenu $frontendMenu): View
    {
        return view('frontend::admin.frontend-menus.edit', array_merge(
            ['menu' => $frontendMenu],
            $this->formData($frontendMenu)
        ));
    }

    public function update(Request $request, FrontendMenu $frontendMenu): RedirectResponse
    {
        $this->service->update($frontendMenu, $this->validateMenu($request, $frontendMenu));

        return redirect()
            ->route('admin.frontend-menus.edit', $frontendMenu)
            ->with('success', __('Frontend menu updated successfully.'));
    }

    public function destroy(FrontendMenu $frontendMenu): RedirectResponse
    {
        try {
            $this->service->delete($frontendMenu);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('admin.frontend-menus.index')
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first());
        }

        return redirect()
            ->route('admin.frontend-menus.index')
            ->with('success', __('Frontend menu deleted successfully.'));
    }

    public function publish(FrontendMenu $frontendMenu): RedirectResponse
    {
        $menu = $this->service->togglePublished($frontendMenu);

        return back()->with('success', $menu->status === 'published'
            ? __('Menu published successfully.')
            : __('Menu moved back to draft successfully.'));
    }

    protected function validateMenu(Request $request, ?FrontendMenu $menu = null): array
    {
        $slug = $request->input('slug') ?: Str::slug((string) $request->input('name'));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('frontend_menus', 'slug')->ignore($menu?->id)],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'items_payload' => ['nullable', 'string'],
        ]);

        $validated['slug'] = $slug;

        return $validated;
    }

    protected function formData(?FrontendMenu $menu = null): array
    {
        $editorItems = old('items_payload')
            ? (json_decode((string) old('items_payload'), true) ?: [])
            : $this->service->initialEditorState($menu);

        return [
            'pageOptions' => Page::query()
                ->where('status', '!=', 'archived')
                ->orderBy('title')
                ->get()
                ->mapWithKeys(fn (Page $page) => [
                    $page->id => $page->title.' ('.($page->is_home ? '/' : '/'.$page->slug).')',
                ])
                ->all(),
            'editorItems' => $editorItems,
            'slotDefinitions' => $this->slots->all(),
            'usage' => $menu ? $this->assignments->usageForMenu($menu) : [],
        ];
    }
}
