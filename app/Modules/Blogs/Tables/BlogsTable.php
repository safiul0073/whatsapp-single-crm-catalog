<?php

namespace App\Modules\Blogs\Tables;

use App\Modules\Shared\Support\Tables\TableAction;
use App\Modules\Shared\Support\Tables\TableBulkAction;
use App\Modules\Shared\Support\Tables\TableColumn;
use App\Modules\Shared\Support\Tables\TableDefinition;

class BlogsTable
{
    public static function make(): TableDefinition
    {
        return TableDefinition::make('blogs')
            ->emptyMessage('No blog posts found.')
            ->columns([
                TableColumn::select(),
                TableColumn::text('title', 'Title')
                    ->sortable()
                    ->cellClass('text-sm font-semibold text-neutral-900'),
                TableColumn::text('author_name', 'Author')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::text('category.name', 'Category')
                    ->format(fn ($value) => $value ?: __('Uncategorized'))
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::text('status', 'Publish Status')
                    ->format(fn ($value) => str($value)->headline()->toString())
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::booleanBadge('active', 'Active'),
                TableColumn::date('published_at', 'Published')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-600'),
            ])
            ->actions([
                TableAction::link('edit', fn ($blog) => route('admin.blogs.edit', $blog), 'Edit')
                    ->icon('pencil-simple'),
                TableAction::toggleStatus(fn ($blog) => route('admin.blogs.toggle-status', $blog), 'active')
                    ->icon('power')
                    ->activeLabel('Deactivate')
                    ->inactiveLabel('Activate')
                    ->confirmTitle(fn ($blog) => $blog->active ? __('Deactivate blog post?') : __('Activate blog post?'))
                    ->confirmMessage(fn ($blog) => $blog->active
                        ? __('Are you sure you want to deactivate \':title\'?', ['title' => $blog->title])
                        : __('Are you sure you want to activate \':title\'?', ['title' => $blog->title])),
                TableAction::delete(href: fn ($blog) => route('admin.blogs.destroy', $blog))
                    ->icon('trash')
                    ->confirmTitle(__('Delete blog post?'))
                    ->confirmMessage(fn ($blog) => __('Are you sure you want to delete \':title\'? This action cannot be undone.', ['title' => $blog->title])),
            ])
            ->bulkActions(
                TableBulkAction::make('blogs')
                    ->deleteAction(route('admin.blogs.bulk-delete'))
            );
    }
}
