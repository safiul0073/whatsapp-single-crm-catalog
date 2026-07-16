<?php

namespace App\Modules\Shared\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Provides CSV import controller actions.
 *
 * Usage:
 *   class ProductsController extends Controller
 *   {
 *       use HasCrudActions, HasImportActions;
 *
 *       public static function middleware(): array
 *       {
 *           return [
 *               ...static::crudMiddleware('products'),
 *               ...static::importMiddleware('products'),
 *           ];
 *       }
 *   }
 *
 * The service must use the HasImport trait.
 */
trait HasImportActions
{
    /**
     * Generate import permission middleware.
     */
    protected static function importMiddleware(string $resource): array
    {
        return [
            new Middleware("permission:{$resource}.create", only: ['importPreview', 'import']),
        ];
    }

    /**
     * Preview CSV file contents for column mapping.
     */
    public function importPreview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $preview = $this->service->previewCsv($request->file('file'));

        return response()->json($preview);
    }

    /**
     * Import records from a CSV file.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'column_map' => ['nullable', 'array'],
        ]);

        $result = $this->service->importCsv(
            $request->file('file'),
            $request->input('column_map')
        );

        return response()->json($result);
    }
}
