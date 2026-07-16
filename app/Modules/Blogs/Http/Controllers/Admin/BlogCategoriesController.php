<?php

namespace App\Modules\Blogs\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Blogs\Http\Requests\StoreBlogCategoryRequest;
use App\Modules\Blogs\Http\Requests\UpdateBlogCategoryRequest;
use App\Modules\Blogs\Services\BlogCategoriesService;
use App\Modules\Blogs\Tables\BlogCategoriesTable;
use App\Modules\Shared\Support\Tables\TableDefinition;
use App\Modules\Shared\Traits\HasCrudActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class BlogCategoriesController extends Controller implements HasMiddleware
{
    use HasCrudActions;

    protected string $viewPath = 'blogs::admin.blog-categories';

    protected string $routePrefix = 'admin.blog-categories';

    protected string $resourceName = 'blogCategories';

    public static function middleware(): array
    {
        return static::crudMiddleware('blog-categories');
    }

    public function __construct(
        protected BlogCategoriesService $service
    ) {}

    public function store(StoreBlogCategoryRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()
            ->route($this->routePrefix.'.index')
            ->with('success', __('Blog category created successfully.'));
    }

    public function update(UpdateBlogCategoryRequest $request, $record): RedirectResponse
    {
        $record = $this->resolveRecord($record);
        $this->service->update($record, $request->validated());

        return redirect()
            ->route($this->routePrefix.'.index')
            ->with('success', __('Blog category updated successfully.'));
    }

    protected function tableDefinition(Request $request): ?TableDefinition
    {
        return BlogCategoriesTable::make();
    }
}
