<?php

namespace App\Modules\Blogs\Tables;

use App\Modules\Shared\Support\Tables\TableAction;
use App\Modules\Shared\Support\Tables\TableBulkAction;
use App\Modules\Shared\Support\Tables\TableColumn;
use App\Modules\Shared\Support\Tables\TableDefinition;

class BlogCategoriesTable
{
    public static function make(): TableDefinition
    {
        return TableDefinition::make('blogCategories')
            ->emptyMessage('No blog categories found.')
            ->columns([
                TableColumn::select(),
                TableColumn::text('name', 'Name')
                    ->sortable()
                    ->cellClass('text-sm font-semibold text-neutral-900'),
                TableColumn::text('slug', 'Slug')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::number('posts_count', 'Posts')
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::number('sort_order', 'Order')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::booleanBadge('active', 'Active'),
            ])
            ->actions([
                TableAction::modal('edit', fn ($category) => "editBlogCategoryModal-{$category->id}", 'Edit')
                    ->icon('pencil-simple'),
                TableAction::toggleStatus(fn ($category) => route('admin.blog-categories.toggle-status', $category), 'active')
                    ->icon('power')
                    ->activeLabel('Deactivate')
                    ->inactiveLabel('Activate')
                    ->confirmTitle(fn ($category) => $category->active ? __('Deactivate blog category?') : __('Activate blog category?'))
                    ->confirmMessage(fn ($category) => $category->active
                        ? __('Are you sure you want to deactivate \':name\'?', ['name' => $category->name])
                        : __('Are you sure you want to activate \':name\'?', ['name' => $category->name])),
                TableAction::delete(href: fn ($category) => route('admin.blog-categories.destroy', $category))
                    ->icon('trash')
                    ->confirmTitle(__('Delete blog category?'))
                    ->confirmMessage(fn ($category) => __('Are you sure you want to delete \':name\'? Existing posts will keep working without a category.', ['name' => $category->name])),
            ])
            ->bulkActions(
                TableBulkAction::make('blogCategories')
                    ->deleteAction(route('admin.blog-categories.bulk-delete'))
            );
    }
}
