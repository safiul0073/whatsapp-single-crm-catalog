<?php

namespace App\Modules\Staffs\Tables;

use App\Modules\Shared\Support\Tables\TableAction;
use App\Modules\Shared\Support\Tables\TableBulkAction;
use App\Modules\Shared\Support\Tables\TableColumn;
use App\Modules\Shared\Support\Tables\TableDefinition;

class StaffsTable
{
    public static function make(): TableDefinition
    {
        return TableDefinition::make('staffs')
            ->emptyMessage('No staff members found.')
            ->bulkActions(
                TableBulkAction::make('staffs')
                    ->deleteAction(route('admin.staffs.bulk-delete'))
                    ->toggleAction(route('admin.staffs.bulk-toggle-status'))
            )
            ->columns([
                TableColumn::select()->headerClass('w-10'),
                TableColumn::text('name', 'Name')
                    ->sortable()
                    ->cellClass('text-sm font-bold text-neutral-950'),
                TableColumn::text('email', 'Email')
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::text('phone', 'Phone')
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::booleanBadge('is_active', 'Status'),
                TableColumn::date('created_at', 'Created')
                    ->cellClass('text-sm text-neutral-400'),
            ])
            ->actions([
                TableAction::link('view', fn ($staff) => route('admin.staffs.show', $staff), 'View')
                    ->icon('eye'),
                TableAction::link('edit', fn ($staff) => route('admin.staffs.edit', $staff), 'Edit')
                    ->icon('pencil-simple'),
                TableAction::delete(href: fn ($staff) => route('admin.staffs.destroy', $staff))
                    ->icon('trash')
                    ->confirmTitle(__('Delete Staff?'))
                    ->confirmMessage(fn ($staff) => __('Are you sure you want to delete \':name\'? This action cannot be undone.', ['name' => $staff->name])),
            ]);
    }
}
