<?php

return [
    // We generate a new snowflake ID for each event
    // snowflakes include a timestamp relative to this date
    // we don't use the Unix epoch because it's too far in the past
    // and we want these events to be good for a long long long time.
    'snowflake_start_date' => '2019-10-10',
];
