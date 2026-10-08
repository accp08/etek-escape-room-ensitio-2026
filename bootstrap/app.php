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
        // La ruta llamada 'login' es la del participante (el juego). Sin esto,
        // Laravel mandaría allí a quien entre al dashboard sin sesión.
        $middleware->redirectGuestsTo(fn () => route('dashboard.login'));
        $middleware->redirectUsersTo('/dashboard');

        // Detrás de un proxy HTTPS en la misma máquina (nginx, o el proxy de
        // desarrollo), las URL —incluida la del QR— deben salir en https: la
        // cámara del celular solo se puede usar en un origen seguro.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
