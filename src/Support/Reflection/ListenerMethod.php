<?php

namespace Thunk\Verbs\Support\Reflection;

class ListenerMethod
{
    public function __construct(
        public object $listener,
        public string $event_type,
        public string $method_name,
        public bool $once,
    ) {
    }
}
