<?php

namespace App\Modules\Currencies\Tables;

use App\Modules\Shared\Support\Tables\TableAction;
use App\Modules\Shared\Support\Tables\TableBulkAction;
use App\Modules\Shared\Support\Tables\TableColumn;
use App\Modules\Shared\Support\Tables\TableDefinition;

class CurrenciesTable
{
    public static function make(): TableDefinition
    {
        return TableDefinition::make('currencies')
            ->emptyMessage('No currencies found.')
            ->columns([
                TableColumn::select(),
                TableColumn::text('code', 'Code')
                    ->sortable()
                    ->format(fn ($value, $currency) => $currency->isDefault()
                        ? e($value).' <span class="badge badge-success ml-2">'.__('Default').'</span>'
                        : e($value))
                    ->rawHtml()
                    ->cellClass('text-sm font-semibold text-neutral-900'),
                TableColumn::text('name', 'Name')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-900'),
                TableColumn::text('symbol', 'Symbol')
                    ->cellClass('text-sm text-neutral-900'),
                TableColumn::number('exchange_rate', 'Exchange Rate')
                    ->sortable()
                    ->meta(['decimals' => 8])
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::date('rate_synced_at', 'Synced')
                    ->cellClass('text-sm text-neutral-500'),
                TableColumn::booleanBadge('is_active', 'Status'),
            ])
            ->actions([
                TableAction::modal('edit', fn ($currency) => "editCurrencyModal-{$currency->id}", 'Edit')
                    ->icon('pencil-simple'),
                TableAction::submit('set_default', fn ($currency) => route('admin.currencies.set-default', $currency), 'Set Default')
                    ->icon('star')
                    ->visible(fn ($currency) => ! $currency->isDefault()),
                TableAction::delete(href: fn ($currency) => route('admin.currencies.destroy', $currency))
                    ->icon('trash')
                    ->confirmTitle(__('Delete Currency?'))
                    ->confirmMessage(fn ($currency) => __('Are you sure you want to delete \':name\'? This action cannot be undone.', ['name' => $currency->name])),
                TableAction::toggleStatus(fn ($currency) => route('admin.currencies.toggle-status', $currency))
                    ->icon('power')
                    ->activeLabel('Deactivate')
                    ->inactiveLabel('Activate')
                    ->confirmTitle(fn ($currency) => $currency->is_active ? __('Deactivate Currency?') : __('Activate Currency?'))
                    ->confirmMessage(fn ($currency) => $currency->is_active
                        ? __('Are you sure you want to deactivate \':name\'? This will make it unavailable for transactions.', ['name' => $currency->name])
                        : __('Are you sure you want to activate \':name\'? This will make it available for transactions.', ['name' => $currency->name])),
            ])
            ->bulkActions(
                TableBulkAction::make('currencies')
                    ->deleteAction(route('admin.currencies.bulk-delete'))
            );
    }
}
