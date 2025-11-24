<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \App\Providers\LoggingServiceProvider::class,
        \App\Providers\ConfigCacheServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\RequireDatabaseConnection::class,
            \App\Http\Middleware\LogRequestMiddleware::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // APIリクエストは常にJSONを返す
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
        
        // すべての例外をログに記録（詳細な情報を含む）
        $exceptions->reportable(function (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Unhandled exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'previous' => $e->getPrevious() ? [
                    'class' => get_class($e->getPrevious()),
                    'message' => $e->getPrevious()->getMessage(),
                    'trace' => $e->getPrevious()->getTraceAsString(),
                ] : null,
            ]);
        });
    })->create();

