<?php

namespace Thunk\Verbs\State\Cache;

use Thunk\Verbs\State\Cache\Contracts\ReadableCache;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;

class MultiCache extends InMemoryCache implements ReadableCache, WritableCache
{
}
