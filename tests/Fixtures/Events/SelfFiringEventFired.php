<?php

namespace Thunk\Verbs\Tests\Fixtures\Events;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Console\Input\Input;
use Thunk\Verbs\Event;

class SelfFiringEventFired extends Event
{
    public function __construct(
        public string $name
    ) {
    }
    
    public function onFire()
    {
        $GLOBALS['heard_events'][] = "self-always:{$this->name}";
    }
}
