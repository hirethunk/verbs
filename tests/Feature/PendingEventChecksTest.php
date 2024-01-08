<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;

it('can test authorization on a pending event', function () {
    $event = EventWithMultipleStates::make([
        'special_id' => 1,
        'other_id' => 2,
        'allowed' => true,
    ]);

    $this->assertTrue($event->isAllowed());

    $event = EventWithMultipleStates::make([
        'special_id' => 1,
        'other_id' => 2,
        'allowed' => false,
    ]);

    $this->assertFalse($event->isAllowed());
});

it('can test validation on a pending event', function () {
    SpecialState::factory()->create([
        'name' => 'daniel',
    ], 1);

    OtherState::factory()->create([
        'name' => 'jacob',
    ], 2);

    OtherState::factory()->create([
        'name' => 'bad bad bad',
    ], 3);

    $event = EventWithMultipleStates::make([
        'special_id' => 1,
        'other_id' => 2,
        'allowed' => true,
    ]);

    $this->assertTrue($event->isValid());

    $event = EventWithMultipleStates::make([
        'special_id' => 1,
        'other_id' => 2,
        'allowed' => false,
    ]);

    $this->assertFalse($event->isValid());

    $event = EventWithMultipleStates::make([
        'special_id' => 1,
        'other_id' => 3,
        'allowed' => true,
    ]);

    $this->assertFalse($event->isValid());
});

class EventWithMultipleStates extends Event
{
    #[StateId(SpecialState::class)]
    public int $special_id;

    #[StateId(OtherState::class)]
    public int $other_id;

    public bool $allowed;

    public function authorize()
    {
        $this->assert(
            $this->allowed === true,
            'You are not allowed to do that.',
        );
    }

    public function validate()
    {
        $this->assert(
            $this->allowed === true,
            'Allowed has to be true.'
        );
    }

    public function validateSpecialState(SpecialState $state)
    {
        $this->assert(
            $state->name === 'daniel',
            'Special state name must be daniel.',
        );
    }

    public function validateOtherState(OtherState $state)
    {
        $this->assert(
            $state->name === 'jacob',
            'Other state name must be jacob.',
        );
    }
}

class SpecialState extends State
{
    public string $name;
}

class OtherState extends State
{
    public string $name;
}
