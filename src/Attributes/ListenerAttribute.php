<?php

namespace Thunk\Verbs\Attributes;

use Thunk\Verbs\Events\Dispatcher\Listener;

interface ListenerAttribute
{
    public function applyToListener(Listener $listener): void;
}
