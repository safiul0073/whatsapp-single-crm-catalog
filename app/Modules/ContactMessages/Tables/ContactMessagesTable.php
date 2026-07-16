<?php

namespace App\Modules\ContactMessages\Tables;

use App\Modules\Shared\Support\Tables\TableAction;
use App\Modules\Shared\Support\Tables\TableBulkAction;
use App\Modules\Shared\Support\Tables\TableColumn;
use App\Modules\Shared\Support\Tables\TableDefinition;

class ContactMessagesTable
{
    public static function make(): TableDefinition
    {
        return TableDefinition::make('contact-messages')
            ->emptyMessage('No contact messages found.')
            ->columns([
                TableColumn::select(),
                TableColumn::text('full_name', 'Name')
                    ->value(fn ($message) => $message->full_name)
                    ->link(fn ($message) => route('admin.contact-messages.show', $message))
                    ->cellClass('text-sm font-semibold text-neutral-900'),
                TableColumn::text('email', 'Email')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::text('company', 'Company')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::text('interest', 'Interest')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::badge('status', 'Status')
                    ->meta([
                        'badge_map' => [
                            'new' => ['label' => 'New', 'variant' => 'success'],
                            'read' => ['label' => 'Read', 'variant' => 'info'],
                            'archived' => ['label' => 'Archived', 'variant' => 'neutral'],
                        ],
                    ])
                    ->cellClass('text-sm'),
                TableColumn::date('created_at', 'Submitted')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-600'),
            ])
            ->actions([
                TableAction::link('view', fn ($message) => route('admin.contact-messages.show', $message), 'View')
                    ->icon('eye'),
                TableAction::delete(href: fn ($message) => route('admin.contact-messages.destroy', $message))
                    ->icon('trash')
                    ->confirmTitle(__('Delete Contact Message?'))
                    ->confirmMessage(fn ($message) => __('Are you sure you want to delete the message from :name? This action cannot be undone.', ['name' => $message->full_name])),
            ])
            ->bulkActions(
                TableBulkAction::make('contact-messages')
                    ->deleteAction(route('admin.contact-messages.bulk-delete'))
            );
    }
}
