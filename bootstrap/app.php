<?php

use App\Http\Middleware\NormalizeLineEndings;
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
        // Global, not web-only: the autosave endpoint and the entity forms must
        // agree on line endings or their revisions diff against each other.
        $middleware->append(NormalizeLineEndings::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
