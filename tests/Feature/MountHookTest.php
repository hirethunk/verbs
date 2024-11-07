<?php

use Thunk\Verbs\Attributes\Hooks\On;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\Phase;

it('can modify props on events in the mount phase', function () {
    Verbs::registerListener(Listener::class);

    $e = EventWithNullProp::fire(
        album: 'Tha Carter 2'
    );

    expect($e)
        ->name
        ->toBe('Lil Wayne');
});

class EventWithNullProp extends Event
{
    public ?string $name = null;

    public string $album;
}

class Listener
{
    #[On(Phase::Mount)]
    public static function setNameToLilWayne(EventWithNullProp $event)
    {
        $event->name = 'Lil Wayne';
    }
}
