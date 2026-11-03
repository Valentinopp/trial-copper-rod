<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Neon CLI writes its service variables at the repository root.
// Laravel still reads its own backend/.env for application settings.
if (($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV')) !== 'testing') {
    \Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2), ['.env.local', '.env'])->safeLoad();
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn () => route(auth()->user()->homeRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Konfigurasi error khusus dapat ditambahkan di sini.
    })
    ->create();

// Arahkan direktori storage ke /tmp jika berjalan di lingkungan serverless Vercel
if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']) || getenv('VERCEL')) {
    $app->useStoragePath('/tmp/storage');
}

return $app;
