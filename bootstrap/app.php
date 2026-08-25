<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'menu' => \App\Http\Middleware\CheckMenuAccess::class,
        ]);
        $middleware->redirectGuestsTo('/login');

        // Kotak input angka menampilkan pemisah ribuan sambil diketik, jadi nilai yang
        // terkirim berformat ("1.500.000"). Harus dinormalkan SEBELUM validasi.
        $middleware->web(prepend: [
            \App\Http\Middleware\NormalizeNumericInput::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
