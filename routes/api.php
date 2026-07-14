<?php

use App\Modules\Identity\Presentation\Http\Controllers\AuthController;
use App\Modules\Reporting\Presentation\Http\Controllers\AdminApiController;
use App\Modules\Transaction\Presentation\Http\Controllers\TransactionController;
use App\Modules\Wallet\Presentation\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:authentication');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:authentication');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::delete('/tokens/current', [AuthController::class, 'logout']);
    });
});

Route::prefix('v1/admin')->middleware(['auth:sanctum', 'admin', 'throttle:admin'])->group(function (): void {
    Route::get('/dashboard', [AdminApiController::class, 'dashboard']);
    Route::get('/transactions', [AdminApiController::class, 'transactions']);
    Route::get('/transactions/{transaction}', [AdminApiController::class, 'transaction']);
    Route::get('/ledger/{ledgerTransaction}', [AdminApiController::class, 'ledger']);
    Route::get('/reconciliation', [AdminApiController::class, 'reconciliationReports']);
    Route::post('/reconciliation', [AdminApiController::class, 'runReconciliation']);
    Route::get('/reconciliation/{report}', [AdminApiController::class, 'reconciliationReport']);
});

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:authenticated-api'])->group(function (): void {
    Route::post('/wallets', [WalletController::class, 'store']);
    Route::get('/wallets/{wallet}', [WalletController::class, 'show']);
    Route::post('/wallets/{wallet}/freeze', [WalletController::class, 'freeze']);
    Route::post('/wallets/{wallet}/unfreeze', [WalletController::class, 'unfreeze']);
    Route::post('/transactions/transfers', [TransactionController::class, 'transfer'])->middleware(['throttle:financial', 'idempotency']);
    Route::post('/transactions/deposits', [TransactionController::class, 'deposit'])->middleware(['demo', 'throttle:financial', 'idempotency']);
    Route::post('/transactions/withdrawals', [TransactionController::class, 'withdraw'])->middleware(['throttle:financial', 'idempotency']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::post('/transactions/{transaction}/refunds', [TransactionController::class, 'refund'])->middleware(['throttle:financial', 'idempotency']);
});
