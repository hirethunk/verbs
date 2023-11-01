<?php

use Illuminate\Support\Facades\Artisan;
use Thunk\Verbs\Examples\Counter\Events\IncrementCount;

Artisan::command('count:increment', function () {
    $state = IncrementCount::fire()->state();

    $this->line("Count is now {$state->count}");
});
