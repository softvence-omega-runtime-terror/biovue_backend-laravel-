<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckDataAccess;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi(); 
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'api/v1/*', 
        ]);
        $middleware->alias([
            'data.access' => CheckDataAccess::class,
        ]);
         $middleware->validateCsrfTokens(except: [
            '/stripe/webhook', 
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/v1/payment/webhook',
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();