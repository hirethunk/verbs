<?php

namespace Thunk\Verbs\Attributes;

use Thunk\Verbs\Lifecycle\Listener;

interface ListenerAttribute
{
    public function applyToListener(Listener $listener): void;
}
