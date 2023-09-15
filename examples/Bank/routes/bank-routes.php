<?php

use Illuminate\Support\Facades\Route;
use Thunk\Verbs\Examples\Bank\Http\Controllers\AccountController;

Route::middleware('web')->group(function () {
    Route::post('accounts', [AccountController::class, 'store'])
        ->name('bank.accounts.store');

    Route::post('accounts/{account}/deposits', [AccountController::class, 'deposit'])
        ->name('bank.accounts.deposits.store');

    Route::post('accounts/{account}/withdrawals', [AccountController::class, 'withdraw'])
        ->name('bank.accounts.withdrawals.store');
});
