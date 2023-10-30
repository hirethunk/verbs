<?php

namespace Thunk\Verbs\Attributes\StateDiscovery;

use Thunk\Verbs\Event;
use Thunk\Verbs\State;

interface StateDiscoveryAttribute
{
    public function discoverState(Event $event): State;
}
