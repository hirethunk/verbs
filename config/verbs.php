<?php

return [
    // We generate a new snowflake ID for each event
    // snowflakes include a timestamp relative to this date
    // we don't use the Unix epoch because it's too far in the past
    // and we want these events to be good for a long long long time.
    'snowflake_start_date' => '2000-01-01',
	
	// You can have up to 31 datacenters and 31 workers in each datacenter.
	// To ensure you do not have ID collisions, each machine that dispatches
	// events should have a unique worker ID.
	'snowflake_datacenter_id' => env('SNOWFLAKE_DATACENTER_ID'),
	'snowflake_worker_id' => env('SNOWFLAKE_WORKER_ID'),
];
