<?php

namespace App\Modules\Shared\Traits;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provides CSV export controller action.
 *
 * Usage:
 *   class ProductsController extends Controller
 *   {
 *       use HasCrudActions, HasExportActions;
 *
 *       public static function middleware(): array
 *       {
 *           return [
 *               ...static::crudMiddleware('products'),
 *               ...static::exportMiddleware('products'),
 *           ];
 *       }
 *   }
 *
 * The service must use the HasExport trait.
 */
trait HasExportActions
{
    /**
     * Generate export permission middleware.
     */
    protected static function exportMiddleware(string $resource): array
    {
        return [
            new Middleware("permission:{$resource}.view", only: ['export']),
        ];
    }

    /**
     * Export records as CSV.
     *
     * Supports exporting all (with current filters) or selected IDs.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->getFilters($request);
        $ids = $request->input('ids');

        if (is_array($ids)) {
            $ids = array_map('intval', $ids);
        }

        return $this->service->exportCsv($filters, $ids);
    }
}
