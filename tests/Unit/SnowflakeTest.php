<?php

use Illuminate\Support\Carbon;
use Thunk\Verbs\Facades\Snowflake;

it('generates snowflakes based on the start date', function () {
    $start_date_ms = Carbon::parse('1991-05-01')->getTimestampMs();

    // freeze time to avoid milliseconds of execution time throwing off results
    Carbon::setTestNow(now());

    $now_ms = now()->getTimestampMs();

    $snowflake = Snowflake::setStartTimeStamp($start_date_ms)->id();

    $millisecond_diff_from_start_date = $now_ms - $start_date_ms;

    expect(
        Snowflake::parseId($snowflake, true)['timestamp']
        - $millisecond_diff_from_start_date
    )->toBeLessThan(10)->toBeGreaterThan(-10);
});
