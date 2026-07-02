<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\BalanceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

/*
 | API AWAM /v1 (API-SPEC.md) — JSON sahaja, TIADA middleware 'web'
 | (tiada sesi/CSRF). Pengesahan: Bearer token + X-Client-Key (api.auth),
 | scope per endpoint (api.scope), idempotency untuk POST (api.idem),
 | had kadar per klien (api.throttle), log setiap panggilan (api.log).
 */
Route::prefix('v1')->group(function () {

    // Dokumentasi OpenAPI 3.0 (tanpa auth)
    Route::get('/docs', fn () => redirect('/openapi.yaml'))->name('api.v1.docs');

    // Token (tanpa auth — log sahaja). E1: had kadar (10/min per IP) elak brute-force secret.
    Route::post('/auth/token', [AuthTokenController::class, 'token'])
        ->middleware(['api.log', 'throttle:10,1'])
        ->name('api.v1.token');

    Route::middleware(['api.log', 'api.auth', 'api.throttle'])->group(function () {

        // ---------- BACA (spec §4) ----------
        Route::get('/accounts', [AccountController::class, 'index'])
            ->middleware('api.scope:read:accounts');

        Route::get('/transactions', [TransactionController::class, 'index'])
            ->middleware('api.scope:read:transactions');
        Route::get('/transactions/{id}', [TransactionController::class, 'show'])
            ->whereNumber('id')->middleware('api.scope:read:transactions');

        Route::get('/balances', [BalanceController::class, 'index'])
            ->middleware('api.scope:read:balances');

        Route::middleware('api.scope:read:reports')->group(function () {
            Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance']);
            Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement']);
            Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet']);
        });
        Route::get('/reports/by-program', [ReportController::class, 'byProgram'])
            ->middleware('api.scope:read:programs');

        // ---------- TULIS (spec §5) — Idempotency-Key WAJIB ----------
        Route::post('/receipts', [ReceiptController::class, 'store'])
            ->middleware(['api.scope:write:receipts', 'api.idem']);

        Route::post('/payments', [PaymentController::class, 'store'])
            ->middleware(['api.scope:write:payments', 'api.idem']);

        // Scope void bergantung jenis (write:receipts/write:payments) — disemak dalam controller
        Route::post('/transactions/{id}/void', [TransactionController::class, 'void'])
            ->whereNumber('id')->middleware('api.idem');
    });
});
