<?php

namespace Thunk\Verbs\Attributes;

use Thunk\Verbs\Events\Listener;

interface ListenerAttribute
{
    public function applyToListener(Listener $listener): void;
}
