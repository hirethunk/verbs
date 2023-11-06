<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Thunk\Verbs\Examples\Monopoly\Http\Controllers\GameController;

View::addNamespace('monopoly', __DIR__.'/../views');
Blade::componentNamespace('Thunk\\Verbs\\Examples\\Monopoly\\View\\Components', 'monopoly');

Route::redirect('/', '/monopoly/games');
Route::get('/games', [GameController::class, 'index']);
Route::post('/games', [GameController::class, 'store']);
Route::get('/games/{game_id}', [GameController::class, 'show']);
