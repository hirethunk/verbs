<?php

namespace Thunk\Verbs\Snowflakes;

use Illuminate\Support\Facades\Cache;
use Thunk\Verbs\Contracts\ResolvesSequences;

class CacheSequenceResolver implements ResolvesSequences
{
    public function next(int $timestamp): int
    {
        $key = "snowflake-seq:{$timestamp}";

        Cache::add($key, 0, now()->addSeconds(10));

        return Cache::increment($key) - 1;
    }
}
