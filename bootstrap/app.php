<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Modules\Identity\Presentation\Http\Middleware\EnsureAdministrator;
use App\Modules\Identity\Presentation\Http\Middleware\RequirePermission;
use App\Modules\Transaction\Domain\Exceptions\TransactionProcessingException;
use App\Modules\Transaction\Presentation\Http\Middleware\RequireDemoMode;
use App\Modules\Transaction\Presentation\Http\Middleware\RequireIdempotencyKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AddSecurityHeaders::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->alias([
            'permission' => RequirePermission::class,
            'idempotency' => RequireIdempotencyKey::class,
            'admin' => EnsureAdministrator::class,
            'demo' => RequireDemoMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (TransactionProcessingException $exception): void {
            Log::warning('Financial transaction processing failed.', [
                'transaction_id' => $exception->transactionId,
                'exception' => $exception::class,
            ]);
        });
        $exceptions->render(function (TransactionProcessingException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage(), 'code' => 'TRANSACTION_FAILED', 'transaction_id' => $exception->transactionId], 422);
            }
        });
    })->create();
