<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Thunk\Verbs\Examples\Monopoly\Http\Controllers\AuthController;
use Thunk\Verbs\Examples\Monopoly\Http\Controllers\GameController;
use Thunk\Verbs\Examples\Monopoly\Http\Controllers\PlayerController;

View::addNamespace('monopoly', __DIR__.'/../views');
Blade::componentNamespace('Thunk\\Verbs\\Examples\\Monopoly\\View\\Components', 'monopoly');

Route::redirect('/', '/monopoly/games');
Route::get('/login', AuthController::class);
Route::post('/login', AuthController::class);
Route::get('/games', [GameController::class, 'index']);
Route::post('/games', [GameController::class, 'store']);
Route::get('/games/{game_id}', [GameController::class, 'show']);
Route::post('/games/{game_id}/players', [PlayerController::class, 'store']);
