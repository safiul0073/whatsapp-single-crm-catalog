<?php

namespace App\Modules\SupportTickets\Tables;

use App\Modules\Shared\Support\Tables\TableAction;
use App\Modules\Shared\Support\Tables\TableBulkAction;
use App\Modules\Shared\Support\Tables\TableColumn;
use App\Modules\Shared\Support\Tables\TableDefinition;

class SupportTicketsTable
{
    public static function make(): TableDefinition
    {
        return TableDefinition::make('support-tickets')
            ->emptyMessage('No support tickets found.')
            ->columns([
                TableColumn::select(),
                TableColumn::text('id', 'Ticket')
                    ->format(fn ($value, $ticket) => $ticket->formatted_id)
                    ->link(fn ($ticket) => route('admin.support-tickets.show', $ticket))
                    ->cellClass('text-sm font-mono font-semibold text-neutral-900'),
                TableColumn::text('subject', 'Subject')
                    ->sortable()
                    ->link(fn ($ticket) => route('admin.support-tickets.show', $ticket))
                    ->cellClass('text-sm font-semibold text-neutral-900'),
                TableColumn::text('user_name', 'User')
                    ->value(fn ($ticket) => $ticket->user?->name ?? '—')
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::badge('priority', 'Priority')
                    ->value(fn ($ticket) => $ticket->priority_label)
                    ->format(fn ($value, $ticket) => $ticket->priority_label)
                    ->cellClass('text-sm'),
                TableColumn::badge('status', 'Status')
                    ->value(fn ($ticket) => $ticket->status_label)
                    ->format(fn ($value, $ticket) => $ticket->status_label)
                    ->cellClass('text-sm'),
                TableColumn::date('last_replied_at', 'Last Reply')
                    ->sortable()
                    ->format(fn ($value) => $value ? $value->diffForHumans() : '—')
                    ->cellClass('text-sm text-neutral-600'),
                TableColumn::date('created_at', 'Created')
                    ->sortable()
                    ->cellClass('text-sm text-neutral-600'),
            ])
            ->actions([
                TableAction::link('view', fn ($ticket) => route('admin.support-tickets.show', $ticket), 'View')
                    ->icon('eye'),
                TableAction::delete(href: fn ($ticket) => route('admin.support-tickets.destroy', $ticket))
                    ->icon('trash')
                    ->confirmTitle(__('Delete Ticket?'))
                    ->confirmMessage(fn ($ticket) => __('Are you sure you want to delete ticket :id? This will remove all replies. This action cannot be undone.', ['id' => $ticket->formatted_id])),
            ])
            ->bulkActions(
                TableBulkAction::make('support-tickets')
                    ->deleteAction(route('admin.support-tickets.bulk-delete'))
            );
    }
}
