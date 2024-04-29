<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\State;

it('can test authorization on a pending event', function () {
    $event = EventWithMultipleStates::make([
        'special_id' => 1,
        'other_id' => 2,
        'allowed' => true,
    ]);

    $this->assertTrue($event->isAuthorized());

    $event = EventWithMultipleStates::make([
        'special_id' => 1,
        'other_id' => 2,
        'allowed' => false,
    ]);

    $this->assertFalse($event->isAuthorized());
});

it('supports boolean authorization', function () {
    $event = EventWithBooleanAuth::make([
        'allowed' => true,
    ]);

    $this->assertTrue($event->isAuthorized());

    $event = EventWithBooleanAuth::make([
        'allowed' => false,
    ]);

    $this->assertFalse($event->isAuthorized());
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

it('can not throw an exception if the event is fired using fireIfValid', function () {
    SpecialState::factory()->id(1)->create([
        'name' => 'daniel',
    ]);

    // this is invalid and will cause validation to fail
    OtherState::factory()->id(3)->create([
        'name' => 'john',
    ]);

    expect(
        fn () => EventWithMultipleStates::fireIfValid([
            'special_id' => 1,
            'other_id' => 3,
            'allowed' => true,
        ])
    )->not->toThrow(EventNotValid::class);

    expect(
        fn () => EventWithMultipleStates::fire([
            'special_id' => 1,
            'other_id' => 3,
            'allowed' => true,
        ])
    )->toThrow(EventNotValid::class);
});

class EventWithBooleanAuth extends Event
{
    public bool $allowed;

    public function authorize()
    {
        return $this->allowed;
    }
}

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
