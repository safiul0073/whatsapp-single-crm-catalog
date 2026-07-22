<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

foreach (['SCRIPT_NAME', 'PHP_SELF'] as $serverKey) {
    if (isset($_SERVER[$serverKey])) {
        $_SERVER[$serverKey] = str_replace('/public/index.php', '/index.php', $_SERVER[$serverKey]);
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
