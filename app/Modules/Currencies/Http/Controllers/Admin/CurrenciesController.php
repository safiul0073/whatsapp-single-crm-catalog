<?php

namespace App\Modules\Currencies\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Currencies\Http\Requests\StoreCurrencyRequest;
use App\Modules\Currencies\Http\Requests\UpdateCurrencyRequest;
use App\Modules\Currencies\Services\CurrenciesService;
use App\Modules\Currencies\Tables\CurrenciesTable;
use App\Modules\Shared\Support\Tables\TableDefinition;
use App\Modules\Shared\Traits\HasCrudActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CurrenciesController extends Controller implements HasMiddleware
{
    use HasCrudActions;

    protected string $viewPath = 'currencies::admin';

    protected string $routePrefix = 'admin.currencies';

    protected string $resourceName = 'currencies';

    public static function middleware(): array
    {
        return array_merge(static::crudMiddleware('currencies'), [
            new Middleware('permission:currencies.edit', only: ['syncRates', 'setDefault']),
        ]);
    }

    public function __construct(
        protected CurrenciesService $service
    ) {}

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()
            ->route("{$this->routePrefix}.index")
            ->with('success', __('Currency created successfully'));
    }

    public function update(UpdateCurrencyRequest $request, $record): RedirectResponse
    {
        $record = $this->resolveRecord($record);
        $this->service->update($record, $request->validated());

        return redirect()
            ->route("{$this->routePrefix}.index")
            ->with('success', __('Currency updated successfully'));
    }

    public function syncRates(): RedirectResponse
    {
        $result = $this->service->syncExchangeRates();

        return back()->with($result['success'] ? 'success' : 'error', __($result['message']));
    }

    public function setDefault($record): RedirectResponse
    {
        $record = $this->resolveRecord($record);
        $this->service->setDefault($record);

        return back()->with('success', __('Default currency updated successfully'));
    }

    protected function tableDefinition(Request $request): ?TableDefinition
    {
        return CurrenciesTable::make();
    }
}
