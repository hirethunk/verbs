<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Event;

class Store
{
    public function write(array $events)
    {
        return true;
    }
}