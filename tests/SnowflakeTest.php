<?php

use Illuminate\Support\Carbon;
use Thunk\Verbs\Facades\Snowflake;

it('generates snowflakes based on the start ID', function () {
    config(['verbs.snowflake_start_date' => '1991-05-01']);

    Carbon::setTestNow('2018-07-18');

    $snowflake = Snowflake::id();

    dd($snowflake);
});
