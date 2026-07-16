<?php

namespace App\Modules\Shared\Traits;

use App\Modules\Shared\Support\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Provides standard CRUD controller actions for panel controllers.
 *
 * Usage:
 *   class ProductsController extends Controller implements HasMiddleware
 *   {
 *       use HasCrudActions;
 *
 *       protected string $viewPath = 'products::admin';
 *       protected string $routePrefix = 'admin.products';
 *       protected string $resourceName = 'products';   // permission prefix & compact key
 *
 *       public static function middleware(): array
 *       {
 *           return static::crudMiddleware('products');
 *       }
 *
 *       public function __construct(protected ProductsService $service) {}
 *
 *       // Add custom methods as needed...
 *   }
 *
 * To override any method, simply redefine it in your controller.
 * To pass extra data to create/edit views, override formData().
 */
trait HasCrudActions
{
    /**
     * Generate standard CRUD permission middleware array.
     *
     * Returns middleware for: view, create, edit (+ toggleStatus, bulkToggleStatus), delete (+ bulkDelete).
     */
    protected static function crudMiddleware(string $resource): array
    {
        return [
            new Middleware("permission:{$resource}.view", only: ['index', 'show']),
            new Middleware("permission:{$resource}.create", only: ['create', 'store']),
            new Middleware("permission:{$resource}.edit", only: ['edit', 'update', 'toggleStatus', 'bulkToggleStatus']),
            new Middleware("permission:{$resource}.delete", only: ['destroy', 'bulkDelete']),
        ];
    }

    /**
     * Resolve a record from a route parameter.
     *
     * Route model binding uses the route parameter name (e.g. {product}),
     * but trait methods use a generic $record parameter. When the names
     * don't match, Laravel passes the raw ID instead of a Model instance.
     * This method ensures we always have a Model instance.
     */
    protected function resolveRecord($record): Model
    {
        if ($record instanceof Model) {
            return $record;
        }

        return $this->service->findOrFail($record);
    }

    /**
     * Display a paginated list of records.
     *
     * Supports AJAX requests: returns JSON with rendered HTML partial
     * and pagination for dynamic table updates.
     */
    public function index(Request $request): View|RedirectResponse|JsonResponse
    {
        $filters = $this->getFilters($request);
        $perPage = $request->integer('per_page') ?: null;

        $items = $this->service->listPaginated($filters, $perPage);
        $varName = $this->getResourceVariable();
        $table = $this->tableDefinition($request);
        $viewData = array_merge(
            [$varName => $items],
            $table ? ['table' => $table] : [],
            $this->indexViewData($request, $items)
        );

        // AJAX: return table rows HTML + pagination for Alpine datatable
        if ($request->ajax()) {
            $html = $table
                ? view('components.tables.resource-rows', ['definition' => $table, 'items' => $items])->render()
                : view($this->rowsView() ?? "{$this->viewPath}._table-rows", [$varName => $items])->render();
            $pagination = view('components.tables.pagination', ['paginator' => $items])->render();

            return response()->json([
                'html' => $html,
                'pagination' => $pagination,
                'total' => $items->total(),
            ]);
        }

        return view($this->indexView() ?? "{$this->viewPath}.index", $viewData);
    }

    /**
     * Show the create form.
     */
    public function create(): View
    {
        return view("{$this->viewPath}.create", $this->formData());
    }

    /**
     * Store a new record.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = method_exists($request, 'validated')
            ? $request->validated()
            : $request->all();

        $this->service->create($data);

        return redirect()
            ->route("{$this->routePrefix}.index")
            ->with('success', __('Created successfully'));
    }

    /**
     * Display a single record.
     */
    public function show($record): View
    {
        $record = $this->resolveRecord($record);
        $varName = $this->getSingularVariable();

        return view("{$this->viewPath}.show", [$varName => $record]);
    }

    /**
     * Show the edit form.
     */
    public function edit($record): View
    {
        $record = $this->resolveRecord($record);
        $varName = $this->getSingularVariable();

        return view("{$this->viewPath}.edit", array_merge(
            [$varName => $record],
            $this->formData()
        ));
    }

    /**
     * Update an existing record.
     */
    public function update(Request $request, $record): RedirectResponse
    {
        $record = $this->resolveRecord($record);
        $data = method_exists($request, 'validated')
            ? $request->validated()
            : $request->all();

        $this->service->update($record, $data);

        return redirect()
            ->route("{$this->routePrefix}.index")
            ->with('success', __('Updated successfully'));
    }

    /**
     * Delete a record.
     */
    public function destroy($record): RedirectResponse
    {
        $record = $this->resolveRecord($record);
        $this->service->delete($record);

        return redirect()
            ->route("{$this->routePrefix}.index")
            ->with('success', __('Deleted successfully'));
    }

    /**
     * Toggle the active status of a record.
     */
    public function toggleStatus($record): RedirectResponse
    {
        $record = $this->resolveRecord($record);
        $this->service->toggleStatus($record);

        return back()->with('success', __('Status updated successfully'));
    }

    /**
     * Get filters from the request.
     * Override this to add custom filter keys.
     */
    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search'),
            'is_active' => $request->input('is_active'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];
    }

    /**
     * Extra data to pass to create/edit views.
     * Override this to add categories, roles, options, etc.
     *
     * Example:
     *   protected function formData(): array
     *   {
     *       return ['categories' => Category::pluck('name', 'id')];
     *   }
     */
    protected function formData(): array
    {
        return [];
    }

    /**
     * Schema-driven table definition for index pages.
     * Return null to keep legacy row partial rendering.
     */
    protected function tableDefinition(Request $request): ?TableDefinition
    {
        return null;
    }

    /**
     * Override the index view when a screen needs a custom entry point.
     */
    protected function indexView(): ?string
    {
        return null;
    }

    /**
     * Override the row partial for legacy tables.
     */
    protected function rowsView(): ?string
    {
        return null;
    }

    /**
     * Extra data needed by the index view.
     */
    protected function indexViewData(Request $request, $items): array
    {
        return [];
    }

    /**
     * Get the plural variable name for compact() in index views.
     * Derives from $resourceName: 'products' → 'products'
     */
    protected function getResourceVariable(): string
    {
        return $this->resourceName;
    }

    /**
     * Get the singular variable name for show/edit views.
     * Derives from $resourceName: 'products' → 'product'
     */
    protected function getSingularVariable(): string
    {
        $name = $this->resourceName;

        // Simple English singular: remove trailing 's'
        if (str_ends_with($name, 'ies')) {
            return substr($name, 0, -3).'y';
        }

        if (str_ends_with($name, 'ses') || str_ends_with($name, 'xes')) {
            return substr($name, 0, -2);
        }

        if (str_ends_with($name, 's') && ! str_ends_with($name, 'ss')) {
            return substr($name, 0, -1);
        }

        return $name;
    }

    /**
     * Bulk delete multiple records by IDs.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $count = $this->service->bulkDelete($request->input('ids'));

        return response()->json([
            'message' => __(':count records deleted.', ['count' => $count]),
        ]);
    }

    /**
     * Bulk toggle status for multiple records by IDs.
     */
    public function bulkToggleStatus(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $count = $this->service->bulkToggleStatus($request->input('ids'));

        return response()->json([
            'message' => __(':count records updated.', ['count' => $count]),
        ]);
    }
}
