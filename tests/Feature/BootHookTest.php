<?php

use Thunk\Verbs\Attributes\Hooks\On;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\Phase;

it('can modify props on events in the Boot phase', function () {
    app(Dispatcher::class)->register(new BootHookTestListener);

    $e = BootHookTestEvent::fire(
        album: 'Tha Carter 2'
    );

    expect($e)->name->toBe('Lil Wayne');
});

class BootHookTestEvent extends Event
{
    public string $name;

    public string $album;
}

class BootHookTestListener
{
    #[On(Phase::Boot)]
    public function setNameToLilWayne(BootHookTestEvent $event)
    {
        $event->name = 'Lil Wayne';
    }
}
