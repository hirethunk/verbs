<?php

namespace Thunk\Verbs\Tests\Fixtures\Contexts;

use Thunk\Verbs\Context;
use Thunk\Verbs\Event;

class GenericContext extends Context
{
    public array $heard = [];

    public function apply(Event $event)
    {
        $this->heard[] = $event::class;
    }
}
