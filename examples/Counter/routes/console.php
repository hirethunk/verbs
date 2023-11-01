<?php

use Illuminate\Support\Facades\Artisan;
use Thunk\Verbs\Examples\Counter\Events\IncrementCount;
use Thunk\Verbs\Facades\Verbs;

Artisan::command('count:increment', function () {
    $state = IncrementCount::fire()->state();

    Verbs::commit();

    $this->line("{$state->count}");
});
