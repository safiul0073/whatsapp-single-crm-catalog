<?php

namespace App\Modules\Faqs\Tables;

use App\Modules\Shared\Support\Tables\TableAction;
use App\Modules\Shared\Support\Tables\TableBulkAction;
use App\Modules\Shared\Support\Tables\TableColumn;
use App\Modules\Shared\Support\Tables\TableDefinition;

class FaqsTable
{
    public static function make(): TableDefinition
    {
        return TableDefinition::make('faqs')
            ->emptyMessage('No FAQs found.')
            ->columns([
                TableColumn::select(),
                TableColumn::text('question', 'Question')
                    ->sortable()
                    ->cellClass('text-sm font-semibold text-neutral-900'),
                TableColumn::text('status', 'Publish Status')
                    ->format(fn ($value) => str($value)->headline()->toString())
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::booleanBadge('active', 'Active'),
                TableColumn::date('created_at', 'Created')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-600'),
            ])
            ->actions([
                TableAction::modal('edit', fn ($faq) => "editFaqModal-{$faq->id}", 'Edit')
                    ->icon('pencil-simple'),
                TableAction::toggleStatus(fn ($faq) => route('admin.faqs.toggle-status', $faq), 'active')
                    ->icon('power')
                    ->activeLabel('Deactivate')
                    ->inactiveLabel('Activate')
                    ->confirmTitle(fn ($faq) => $faq->active ? __('Deactivate FAQ?') : __('Activate FAQ?'))
                    ->confirmMessage(fn ($faq) => $faq->active
                        ? __('Are you sure you want to deactivate \':question\'?', ['question' => $faq->question])
                        : __('Are you sure you want to activate \':question\'?', ['question' => $faq->question])),
                TableAction::delete(href: fn ($faq) => route('admin.faqs.destroy', $faq))
                    ->icon('trash')
                    ->confirmTitle(__('Delete FAQ?'))
                    ->confirmMessage(fn ($faq) => __('Are you sure you want to delete \':question\'? This action cannot be undone.', ['question' => $faq->question])),
            ])
            ->bulkActions(
                TableBulkAction::make('faqs')
                    ->deleteAction(route('admin.faqs.bulk-delete'))
            );
    }
}
