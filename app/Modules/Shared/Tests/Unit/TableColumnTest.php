<?php

namespace App\Modules\Shared\Tests\Unit;

use App\Modules\Shared\Support\Tables\TableColumn;
use PHPUnit\Framework\TestCase;

class TableColumnTest extends TestCase
{
    public function test_it_keeps_header_and_data_th_labels_in_sync(): void
    {
        $column = TableColumn::text('exchange_rate', 'Exchange Rate');

        $this->assertSame('Exchange Rate', $column->label());
        $this->assertSame('Exchange Rate', $column->dataTh());
    }

    public function test_it_supports_sort_configuration_and_sort_mapping(): void
    {
        $column = TableColumn::text('status_label', 'Status')->sortable(true, 'is_active');

        $this->assertTrue($column->isSortable());
        $this->assertSame('is_active', $column->sortField());
    }

    public function test_it_supports_formatters_and_custom_visibility(): void
    {
        $record = (object) ['amount' => 19.5, 'visible' => false];

        $column = TableColumn::number('amount', 'Amount')
            ->format(fn ($value) => '$'.number_format($value, 2))
            ->visible(fn ($item) => $item?->visible !== false);

        $this->assertSame('$19.50', $column->formatValue($record));
        $this->assertFalse($column->isVisible($record));
        $this->assertTrue($column->isVisible((object) ['visible' => true]));
    }

    public function test_it_tracks_custom_cell_view_configuration(): void
    {
        $column = TableColumn::view('customer', 'Customer', 'payments.columns.customer')
            ->cellView('payments.columns.customer', fn ($record, $value) => ['accent' => $record->id, 'value' => $value]);

        $record = (object) ['id' => 8, 'customer' => 'Jane'];

        $this->assertSame('payments.columns.customer', $column->viewName());
        $this->assertSame(
            ['accent' => 8, 'value' => 'Jane'],
            $column->resolveViewData($record, 'Jane')
        );
    }

    public function test_it_supports_linked_columns(): void
    {
        $record = (object) ['id' => 10, 'code' => 'USD'];

        $column = TableColumn::text('code', 'Code')
            ->link(fn ($item) => "/currencies/{$item->id}/edit", true);

        $this->assertTrue($column->hasLink());
        $this->assertSame('/currencies/10/edit', $column->resolveHref($record));
        $this->assertTrue($column->shouldOpenInNewTab());
    }
}
