<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Event;

enum Phase
{
    case Authorize;
    case Validate;
    case Apply;
    case Fired;
    case Handle;
    case Replay;
}
