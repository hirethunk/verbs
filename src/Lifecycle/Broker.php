<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Event;
use Thunk\Verbs\Support\Reflector;

class Broker
{
    public function fire(Event $event)
    {
        $states = $this->enumerateStates($event);

        $states->each(fn($state) => Guards::for($event, $state)->check());
        $states->each(fn ($state) => app(Dispatcher::class)->apply($event, $state));
        
        app(EventQueue::class)->queue($event);
		
		return $event;
    }

    public function enumerateStates(Event $event)
    {
        return Reflector::getPublicStateProperties($event)
            ->map(fn ($_, $property_name) => $event->{$property_name});
    }
}
