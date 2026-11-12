<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

$sharedRootFile = dirname(__DIR__).'/SHARED_ROOT';
if (is_file($sharedRootFile)) {
    $sharedRoot = trim(file_get_contents($sharedRootFile));
    $app->useEnvironmentPath($sharedRoot);
    $app->useStoragePath($sharedRoot.'/storage');
    $app->usePublicPath($sharedRoot.'/public');
}

return $app;
