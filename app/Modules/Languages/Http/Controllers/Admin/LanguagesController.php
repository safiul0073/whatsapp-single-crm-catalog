<?php

namespace App\Modules\Languages\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Languages\Http\Requests\StoreLanguageRequest;
use App\Modules\Languages\Http\Requests\UpdateLanguageRequest;
use App\Modules\Languages\Models\Language;
use App\Modules\Languages\Services\LanguagesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class LanguagesController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:languages.view', only: ['index']),
            new Middleware('permission:languages.create', only: ['create', 'store']),
            new Middleware('permission:languages.edit', only: ['edit', 'update', 'toggleStatus', 'setDefault', 'translations', 'updateTranslations']),
            new Middleware('permission:languages.delete', only: ['destroy']),
        ];
    }

    public function __construct(
        protected LanguagesService $service
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'search' => $request->get('search'),
            'is_active' => $request->get('is_active'),
            'sort_by' => $request->get('sort_by', 'sort_order'),
            'sort_order' => $request->get('sort_order', 'asc'),
        ];

        $languages = $this->service->listPaginated($filters);

        return view('languages::admin.index', compact('languages'));
    }

    public function create(): View
    {
        return view('languages::admin.create');
    }

    public function store(StoreLanguageRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()
            ->route('admin.languages.index')
            ->with('success', 'Language created successfully.');
    }

    public function edit(Language $language): View
    {
        return view('languages::admin.edit', compact('language'));
    }

    public function update(UpdateLanguageRequest $request, Language $language): RedirectResponse
    {
        $this->service->update($language, $request->validated());

        return redirect()
            ->route('admin.languages.index')
            ->with('success', 'Language updated successfully.');
    }

    public function destroy(Language $language): RedirectResponse
    {
        try {
            $this->service->delete($language);

            return redirect()
                ->route('admin.languages.index')
                ->with('success', 'Language deleted successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function toggleStatus(Language $language): RedirectResponse
    {
        try {
            $this->service->toggleStatus($language);

            return back()->with('success', 'Language status updated successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function setDefault(Language $language): RedirectResponse
    {
        $this->service->setDefault($language);

        return back()->with('success', "{$language->name} set as default language.");
    }

    public function translations(Language $language): View
    {
        $sourceKeys = $this->service->getSourceKeys();
        $translations = $this->service->getTranslations($language->code);

        return view('languages::admin.translations', compact('language', 'sourceKeys', 'translations'));
    }

    public function updateTranslations(Request $request, Language $language): RedirectResponse
    {
        $translations = $request->input('translations', []);

        $this->service->saveTranslations($language->code, $translations);

        return redirect()
            ->route('admin.languages.translations', $language)
            ->with('success', 'Translations saved successfully.');
    }
}
