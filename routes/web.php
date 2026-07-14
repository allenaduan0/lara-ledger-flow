<?php

use App\Modules\Identity\Presentation\Http\Controllers\AdminSessionController;
use App\Modules\Reporting\Presentation\Http\Controllers\AdminWebController;
use App\Modules\Wallet\Presentation\Http\Controllers\CustomerPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route(auth()->user()->hasRole('administrator') ? 'admin.dashboard' : 'customer.dashboard') : redirect()->route('login');
});

Route::middleware(['guest', 'throttle:authentication'])->group(function (): void {
    Route::get('/login', [AdminSessionController::class, 'create'])->name('login');
    Route::post('/login', [AdminSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [AdminSessionController::class, 'register'])->name('register');
    Route::post('/register', [AdminSessionController::class, 'createAccount'])->name('register.store');
    Route::redirect('/admin/login', '/login')->name('admin.login');
});

Route::prefix('app')->name('customer.')->middleware(['auth', 'throttle:authenticated-api'])->group(function (): void {
    Route::get('/', [CustomerPortalController::class, 'dashboard'])->name('dashboard');
    Route::post('/wallets', [CustomerPortalController::class, 'createWallet'])->name('wallets.store');
    Route::get('/wallets/{wallet}', [CustomerPortalController::class, 'wallet'])->name('wallets.show');
    Route::post('/wallets/{wallet}/freeze', [CustomerPortalController::class, 'freeze'])->name('wallets.freeze');
    Route::post('/wallets/{wallet}/unfreeze', [CustomerPortalController::class, 'unfreeze'])->name('wallets.unfreeze');
    Route::get('/transactions', [CustomerPortalController::class, 'transactions'])->name('transactions.index');
    Route::get('/transactions/new/{type}', [CustomerPortalController::class, 'movementForm'])->name('transactions.create');
    Route::post('/transactions/transfers', [CustomerPortalController::class, 'transfer'])->middleware('throttle:financial')->name('transactions.transfer');
    Route::post('/transactions/deposits', [CustomerPortalController::class, 'deposit'])->middleware(['demo', 'throttle:financial'])->name('transactions.deposit');
    Route::post('/transactions/withdrawals', [CustomerPortalController::class, 'withdraw'])->middleware('throttle:financial')->name('transactions.withdrawal');
    Route::get('/transactions/{transaction}', [CustomerPortalController::class, 'transaction'])->name('transactions.show');
    Route::post('/transactions/{transaction}/refunds', [CustomerPortalController::class, 'refund'])->middleware('throttle:financial')->name('transactions.refund');
    Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'throttle:admin'])->group(function (): void {
    Route::get('/', [AdminWebController::class, 'dashboard'])->name('dashboard');
    Route::get('/transactions', [AdminWebController::class, 'transactions'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [AdminWebController::class, 'transaction'])->name('transactions.show');
    Route::get('/ledger/{ledgerTransaction}', [AdminWebController::class, 'ledger'])->name('ledger.show');
    Route::get('/reconciliation', [AdminWebController::class, 'reconciliationReports'])->name('reconciliation.index');
    Route::post('/reconciliation', [AdminWebController::class, 'runReconciliation'])->name('reconciliation.run');
    Route::get('/reconciliation/{report}', [AdminWebController::class, 'reconciliationReport'])->name('reconciliation.show');
    Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');
});
