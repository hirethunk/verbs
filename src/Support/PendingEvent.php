<?php

namespace Thunk\Verbs\Support;

use Thunk\Verbs\Context;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Broker;

class PendingEvent
{
    protected ?Context $context = null;

    public function __construct(
        protected string $event_type
    ) {
    }

    public function withContext(Context $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function fire(...$args): Event
    {
        if (1 === count($args) && $args[0] instanceof Event) {
            $event = $args[0];
        } else {
            $event = new $this->event_type(...$args);
        }

        if ($this->context) {
            $event->context_id = $this->context->id;
        }

        Broker::originate($event, $this->context);

        return $event;
    }
}
