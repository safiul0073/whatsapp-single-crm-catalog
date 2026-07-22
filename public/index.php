<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (str_starts_with($requestPath, '/public')) {
    $targetPath = substr($requestPath, strlen('/public')) ?: '/';
    $targetPath = '/'.ltrim($targetPath, '/');

    if (trim($targetPath, '/') === ($_SERVER['HTTP_HOST'] ?? '')) {
        $targetPath = '/';
    }

    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $location = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        .'://'.($_SERVER['HTTP_HOST'] ?? 'localhost')
        .$targetPath
        .($queryString !== '' ? "?{$queryString}" : '');

    header("Location: {$location}", true, 301);
    exit;
}

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
