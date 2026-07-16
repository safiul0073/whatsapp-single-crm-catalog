<?php

namespace App\Panels\Admin\Tables;

use App\Modules\Shared\Support\Tables\TableAction;
use App\Modules\Shared\Support\Tables\TableColumn;
use App\Modules\Shared\Support\Tables\TableDefinition;

class UsersTable
{
    public static function make(): TableDefinition
    {
        return TableDefinition::make('users')
            ->emptyMessage('No users found.')
            ->columns([
                TableColumn::view('name', 'Name', 'panels.admin.users.columns.name')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-900'),
                TableColumn::text('email', 'Email')
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::booleanBadge('is_active', 'Status'),
                TableColumn::date('created_at', 'Created')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-400'),
            ])
            ->actions([
                TableAction::link('view', fn ($user) => route('admin.users.show', $user), 'View')
                    ->icon('eye'),
                TableAction::link('edit', fn ($user) => route('admin.users.edit', $user), 'Edit')
                    ->icon('pencil-simple'),
                TableAction::delete(href: fn ($user) => route('admin.users.destroy', $user))
                    ->icon('trash')
                    ->confirmTitle(__('Delete User?'))
                    ->confirmMessage(fn ($user) => __('Are you sure you want to delete \':name\'? This action cannot be undone.', ['name' => $user->name])),
            ]);
    }
}
