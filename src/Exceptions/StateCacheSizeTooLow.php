<?php

namespace Thunk\Verbs\Exceptions;

use RuntimeException;

class StateCacheSizeTooLow extends RuntimeException
{
    public function __construct()
    {
        $url = 'https://verbs.thunk.dev/docs/reference/states#content-state-cache';
        $size = config('verbs.state_cache_size', 100);

        parent::__construct("Your 'state_cache_size' config value of '{$size}' is too low. See <{$url}>.");
    }
}
