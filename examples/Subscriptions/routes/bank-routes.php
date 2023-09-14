<?php

use Illuminate\Support\Facades\Route;
use Thunk\Verbs\Examples\Subscriptions\Http\Controllers\AccountController;

Route::post('accounts', [AccountController::class, 'store'])
    ->name('bank.accounts.store');