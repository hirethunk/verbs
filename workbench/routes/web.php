<?php

use Illuminate\Support\Facades\Route;

Route::prefix('monopoly')->group(function () {
    require __DIR__.'/../../examples/Monopoly/routes/web.php';
});
