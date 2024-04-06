<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Contracts\StoresEvents;

class EphemeralEventQueue extends Queue
{
    public function hydrate(array $events)
    {
        $this->event_queue = app(StoresEvents::class)->readEphemeral($events)->toArray();
    }

    public function dehydrate()
    {
        return app(StoresEvents::class)->writeEphemeral($this->getEvents());
    }
}
