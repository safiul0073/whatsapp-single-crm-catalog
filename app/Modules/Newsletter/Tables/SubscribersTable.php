<?php

namespace App\Modules\Newsletter\Tables;

use App\Modules\Shared\Support\Tables\TableAction;
use App\Modules\Shared\Support\Tables\TableBulkAction;
use App\Modules\Shared\Support\Tables\TableColumn;
use App\Modules\Shared\Support\Tables\TableDefinition;

class SubscribersTable
{
    public static function make(): TableDefinition
    {
        return TableDefinition::make('subscribers')
            ->emptyMessage('No subscribers found.')
            ->columns([
                TableColumn::select(),
                TableColumn::text('email', 'Email')
                    ->sortable()
                    ->cellClass('text-sm font-semibold text-neutral-900'),
                TableColumn::booleanBadge('active', 'Status'),
                TableColumn::date('created_at', 'Subscribed At')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-600'),
            ])
            ->actions([
                TableAction::toggleStatus(fn ($subscriber) => route('admin.subscribers.toggle-status', $subscriber), 'active')
                    ->icon('power')
                    ->activeLabel('Deactivate')
                    ->inactiveLabel('Activate')
                    ->confirmTitle(fn ($subscriber) => $subscriber->active ? __('Deactivate Subscriber?') : __('Activate Subscriber?'))
                    ->confirmMessage(fn ($subscriber) => $subscriber->active
                        ? __('Are you sure you want to deactivate \':email\'?', ['email' => $subscriber->email])
                        : __('Are you sure you want to activate \':email\'?', ['email' => $subscriber->email])),
                TableAction::delete(href: fn ($subscriber) => route('admin.subscribers.destroy', $subscriber))
                    ->icon('trash')
                    ->confirmTitle(__('Delete Subscriber?'))
                    ->confirmMessage(fn ($subscriber) => __('Are you sure you want to delete \':email\'? This action cannot be undone.', ['email' => $subscriber->email])),
            ])
            ->bulkActions(
                TableBulkAction::make('subscribers')
                    ->deleteAction(route('admin.subscribers.bulk-delete'))
            );
    }
}
