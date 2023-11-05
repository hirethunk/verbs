<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Event;

enum Phase: string
{
    case Authorize = 'authorize';
    case Validate = 'validate';
    case Apply = 'apply';
    case Fired = 'fired';
    case Handle = 'handle';
    case Replay = 'replay';
}
