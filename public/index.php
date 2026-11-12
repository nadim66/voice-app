<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$root = dirname(__DIR__);
if (is_file($root.'/deploy/maintenance')) {
    http_response_code(503);
    header('Retry-After: 30');
    exit('Updating the application. Please try again shortly.');
}

$basePath = $root;
if (is_file($root.'/deploy/active.json')) {
    $active = json_decode(file_get_contents($root.'/deploy/active.json'), true);
    $revision = $active['revision'] ?? '';
    if (! is_string($revision) || ! preg_match('/\A[a-f0-9]{40}\z/', $revision)) {
        http_response_code(503);
        exit('Application temporarily unavailable.');
    }
    $basePath = $root.'/releases/'.$revision;
}

if (! is_file($basePath.'/vendor/autoload.php')) {
    http_response_code(503);
    exit('Application deployment is not active yet.');
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $root.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $basePath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $basePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
