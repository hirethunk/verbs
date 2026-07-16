<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;

/*
 * Phases::firing() runs Boot → Authorize → Validate → Apply, and the Lifecycle
 * dispatches Authorize and Validate as two distinct phases. These pin that
 * validation actually executes when an event is fired.
 */
it('rejects an event on fire when validation fails', function () {
    ValidatePhaseTestEvent::fire(allowed: false);
})->throws(EventNotValidForCurrentState::class);

it('fires an event when validation passes', function () {
    $event = ValidatePhaseTestEvent::fire(allowed: true);

    expect($event)->toBeInstanceOf(ValidatePhaseTestEvent::class);
});

class ValidatePhaseTestEvent extends Event
{
    public bool $allowed = true;

    public function validate(): void
    {
        $this->assert($this->allowed, 'The event is not allowed.');
    }
}
