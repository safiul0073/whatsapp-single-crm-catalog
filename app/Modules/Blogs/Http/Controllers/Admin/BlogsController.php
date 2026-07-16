<?php

namespace App\Modules\Blogs\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Blogs\Http\Requests\StoreBlogRequest;
use App\Modules\Blogs\Http\Requests\UpdateBlogRequest;
use App\Modules\Blogs\Models\BlogCategory;
use App\Modules\Blogs\Services\BlogsService;
use App\Modules\Blogs\Tables\BlogsTable;
use App\Modules\Shared\Support\Tables\TableDefinition;
use App\Modules\Shared\Traits\HasCrudActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class BlogsController extends Controller implements HasMiddleware
{
    use HasCrudActions;

    protected string $viewPath = 'blogs::admin.blogs';

    protected string $routePrefix = 'admin.blogs';

    protected string $resourceName = 'blogs';

    public static function middleware(): array
    {
        return static::crudMiddleware('blogs');
    }

    public function __construct(
        protected BlogsService $service
    ) {}

    public function store(StoreBlogRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()
            ->route($this->routePrefix.'.index')
            ->with('success', __('Blog post created successfully.'));
    }

    public function update(UpdateBlogRequest $request, $record): RedirectResponse
    {
        $record = $this->resolveRecord($record);
        $this->service->update($record, $request->validated());

        return redirect()
            ->route($this->routePrefix.'.index')
            ->with('success', __('Blog post updated successfully.'));
    }

    protected function tableDefinition(Request $request): ?TableDefinition
    {
        return BlogsTable::make();
    }

    protected function formData(): array
    {
        return [
            'categoryOptions' => BlogCategory::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        ];
    }
}
