<?php

namespace Thunk\Verbs\Events\Dispatcher;

use Closure;

class Listener
{
    public function __construct(
        public Closure $listener,
        public bool $once = false,
    ) {
    }

    public function __invoke(...$args)
    {
        return call_user_func_array($this->listener, $args);
    }
}
