<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStoreIsActive;
use App\Http\Middleware\EnsureUserBelongsToCurrentStore;
use App\Http\Middleware\SyncCurrentStoreWithTenancy;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'store.sync' => SyncCurrentStoreWithTenancy::class,
            'store.member' => EnsureUserBelongsToCurrentStore::class,
            'store.active' => EnsureStoreIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
