<?php

namespace App\Modules\Shared\Tests\Unit;

use App\Modules\Shared\Support\NavigationBuilder;
use PHPUnit\Framework\TestCase;

class NavigationBuilderTest extends TestCase
{
    public function test_it_sorts_items_by_order(): void
    {
        $builder = new NavigationBuilder;

        $builder->group('Management')->item('Second', 'admin.second.*')->order(20);
        $builder->group('Management')->item('First', 'admin.first.*')->order(10);

        $items = $builder->toArray();

        $this->assertSame('First', $items[0]['label']);
        $this->assertSame('Second', $items[1]['label']);
    }
}
