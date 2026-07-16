<?php

namespace App\Modules\Shared\Tests\Unit;

use App\Modules\Shared\Support\Tables\TableAction;
use PHPUnit\Framework\TestCase;

class TableActionTest extends TestCase
{
    public function test_it_resolves_link_actions_with_callbacks(): void
    {
        $record = (object) ['id' => 5, 'locked' => false];

        $action = TableAction::link('edit', fn ($item) => "/records/{$item->id}/edit", 'Edit')
            ->icon(fn ($item) => $item->locked ? 'lock' : 'pencil-simple')
            ->attributes(fn ($item) => ['data-record' => $item->id]);

        $this->assertSame('link', $action->typeName());
        $this->assertSame('/records/5/edit', $action->resolveHref($record));
        $this->assertSame('pencil-simple', $action->resolveIcon($record));
        $this->assertSame(['data-record' => 5], $action->resolveAttributes($record));
    }

    public function test_it_supports_delete_confirmation_metadata(): void
    {
        $record = (object) ['name' => 'Demo'];

        $action = TableAction::delete(href: fn () => '/records/5')
            ->method('DELETE')
            ->confirmTitle(fn ($item) => "Delete {$item->name}?")
            ->confirmMessage(fn ($item) => "{$item->name} will be removed.");

        $this->assertSame('delete', $action->typeName());
        $this->assertSame('/records/5', $action->resolveHref($record));
        $this->assertSame('DELETE', $action->resolveMethod($record));
        $this->assertSame('Delete Demo?', $action->resolveConfirmTitle($record));
        $this->assertSame('Demo will be removed.', $action->resolveConfirmMessage($record));
    }

    public function test_it_supports_visibility_and_disabled_callbacks(): void
    {
        $record = (object) ['editable' => false, 'visible' => true];

        $action = TableAction::button('archive', 'Archive')
            ->visible(fn ($item) => $item->visible)
            ->disabled(fn ($item) => ! $item->editable);

        $this->assertTrue($action->isVisible($record));
        $this->assertTrue($action->isDisabled($record));
    }

    public function test_it_supports_toggle_status_actions_with_stateful_labels(): void
    {
        $activeRecord = (object) ['is_active' => true];
        $inactiveRecord = (object) ['is_active' => false];

        $action = TableAction::toggleStatus(fn () => '/currencies/1/toggle-status')
            ->activeLabel('Deactivate')
            ->inactiveLabel('Activate');

        $this->assertSame('toggle_status', $action->typeName());
        $this->assertSame('POST', $action->resolveMethod($activeRecord));
        $this->assertSame('/currencies/1/toggle-status', $action->resolveHref($activeRecord));
        $this->assertSame('Deactivate', $action->resolveLabel($activeRecord));
        $this->assertSame('Activate', $action->resolveLabel($inactiveRecord));
    }
}
