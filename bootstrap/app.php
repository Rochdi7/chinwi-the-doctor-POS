<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        // The PDF and label URLs belong to the app: a logged-out visitor is
        // sent to the React login, not to a missing "login" route.
        $middleware->redirectGuestsTo('/app/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
