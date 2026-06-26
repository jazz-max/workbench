<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Если приложение работает за reverse-proxy (nginx и т.п.) — раскомментируй
        // и укажи КОНКРЕТНЫЕ IP прокси. Значение '*' доверяет любому прокси и
        // небезопасно при прямом доступе к приложению из интернета.
        // $middleware->trustProxies(at: '*');

        // Опциональная proxy-фича (см. закомментированный ServletController::proxy
        // и роут servlet/*/proxy/*): при включении может понадобиться отключить
        // CSRF для проксируемых запросов. Включай осознанно.
        // $middleware->validateCsrfTokens(except: [
        //     'servlet/*/proxy/*',
        // ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
