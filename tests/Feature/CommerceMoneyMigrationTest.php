<?php

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('preserves money defaults without quoting SQL null during an existing database upgrade', function (mixed $default, string $expectedDefault): void {
    $migration = require base_path('app/Modules/Commerce/Database/Migrations/2026_10_02_135512_upgrade_existing_commerce_for_unified_orders.php');
    $connection = new MySqlConnection(null);
    $connection->useDefaultSchemaGrammar();
    $statements = [];

    Schema::shouldReceive('getColumns')->once()->with('commerce_products')->andReturn([
        ['name' => 'single_piece_price', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => $default],
        ['name' => 'wholesale_price', 'type' => 'decimal(19,4)', 'nullable' => true, 'default' => $default],
    ]);
    Schema::shouldReceive('table')->once()->with('commerce_products', Mockery::type(Closure::class))
        ->andReturnUsing(function (string $table, Closure $callback) use ($connection, &$statements): void {
            $blueprint = new Blueprint($connection, $table);
            $callback($blueprint);
            $statements = $blueprint->toSql();
        });

    (new ReflectionMethod($migration, 'widenMoney'))->invoke($migration, 'commerce_products', ['single_piece_price', 'wholesale_price']);

    expect($statements)->toHaveCount(1)
        ->and($statements[0])->toBe('alter table `commerce_products` modify `single_piece_price` decimal(19, 4) null'.$expectedDefault);
})->with([
    'MySQL null' => [null, ''],
    'MariaDB SQL null' => ['NULL', ''],
    'lowercase SQL null' => ['null', ''],
    'zero default' => ['0.00', " default '0.00'"],
    'quoted MariaDB numeric default' => ["'0.00'", " default '0.00'"],
    'nonzero default' => ['12.50', " default '12.50'"],
]);
