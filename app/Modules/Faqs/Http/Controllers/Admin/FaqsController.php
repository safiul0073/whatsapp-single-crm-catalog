<?php

namespace App\Modules\Faqs\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Faqs\Http\Requests\StoreFaqRequest;
use App\Modules\Faqs\Http\Requests\UpdateFaqRequest;
use App\Modules\Faqs\Services\FaqsService;
use App\Modules\Faqs\Tables\FaqsTable;
use App\Modules\Shared\Support\Tables\TableDefinition;
use App\Modules\Shared\Traits\HasCrudActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class FaqsController extends Controller implements HasMiddleware
{
    use HasCrudActions;

    protected string $viewPath = 'faqs::admin.faqs';

    protected string $routePrefix = 'admin.faqs';

    protected string $resourceName = 'faqs';

    public static function middleware(): array
    {
        return static::crudMiddleware('faqs');
    }

    public function __construct(
        protected FaqsService $service
    ) {}

    public function store(StoreFaqRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()
            ->route($this->routePrefix.'.index')
            ->with('success', __('FAQ created successfully.'));
    }

    public function update(UpdateFaqRequest $request, $record): RedirectResponse
    {
        $record = $this->resolveRecord($record);
        $this->service->update($record, $request->validated());

        return redirect()
            ->route($this->routePrefix.'.index')
            ->with('success', __('FAQ updated successfully.'));
    }

    protected function tableDefinition(Request $request): ?TableDefinition
    {
        return FaqsTable::make();
    }
}
