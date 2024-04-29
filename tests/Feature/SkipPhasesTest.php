<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\State;

$snowflake = 170622032481976320;

it('hits all hooks by default', function () use ($snowflake) {
    $state = HooksState::load($snowflake);

    EventHitHooks::fire(state_id: $snowflake);

    Verbs::commit();

    expect($state->validate)->toBeTrue();
    expect($state->fired)->toBeTrue();
    expect($state->apply)->toBeTrue();
    expect($state->handle)->toBeTrue();
});

it('skips validation when set to', function () use ($snowflake) {
    Verbs::skipPhases(Phase::Validate);

    $state = HooksState::load($snowflake);

    EventHitHooks::fire(state_id: $snowflake);

    Verbs::commit();

    expect($state->validate)->toBeFalse();
    expect($state->fired)->toBeTrue();
    expect($state->apply)->toBeTrue();
    expect($state->handle)->toBeTrue();
});

it('skips apply when set to', function () use ($snowflake) {
    Verbs::skipPhases(Phase::Apply);

    $state = HooksState::load($snowflake);

    EventHitHooks::fire(state_id: $snowflake);

    Verbs::commit();

    expect($state->validate)->toBeTrue();
    expect($state->fired)->toBeTrue();
    expect($state->apply)->toBeFalse();
    expect($state->handle)->toBeTrue();
});

it('skips handle when set to', function () use ($snowflake) {
    Verbs::skipPhases(Phase::Handle);

    $state = HooksState::load($snowflake);

    EventHitHooks::fire(state_id: $snowflake);

    Verbs::commit();

    expect($state->validate)->toBeTrue();
    expect($state->fired)->toBeTrue();
    expect($state->apply)->toBeTrue();
    expect($state->handle)->toBeFalse();
});

it('skips fired when set to', function () use ($snowflake) {
    Verbs::skipPhases(Phase::Fired);

    $state = HooksState::load($snowflake);

    EventHitHooks::fire(state_id: $snowflake);

    Verbs::commit();

    expect($state->validate)->toBeTrue();
    expect($state->fired)->toBeFalse();
    expect($state->apply)->toBeTrue();
    expect($state->handle)->toBeTrue();
});

it('skips all hooks when set to', function () use ($snowflake) {
    Verbs::skipPhases(Phase::Validate, Phase::Apply, Phase::Handle, Phase::Fired);

    $state = HooksState::load($snowflake);

    EventHitHooks::fire(state_id: $snowflake);

    Verbs::commit();

    expect($state->validate)->toBeFalse();
    expect($state->fired)->toBeFalse();
    expect($state->apply)->toBeFalse();
    expect($state->handle)->toBeFalse();
});

it('can reset skipped phases', function () use ($snowflake) {
    Verbs::skipPhases(Phase::Validate, Phase::Apply, Phase::Handle, Phase::Fired);

    $state = HooksState::load($snowflake);

    Verbs::skipPhases();

    EventHitHooks::fire(state_id: $snowflake);

    Verbs::commit();

    expect($state->validate)->toBeTrue();
    expect($state->fired)->toBeTrue();
    expect($state->apply)->toBeTrue();
    expect($state->handle)->toBeTrue();
});

class HooksState extends State
{
    public $validate = false;

    public $fired = false;

    public $apply = false;

    public $handle = false;
}

class EventHitHooks extends Event
{
    #[StateId(HooksState::class)]
    public int $state_id;

    public function validate(HooksState $state): void
    {
        $state->validate = true;
    }

    public function fired(HooksState $state): void
    {
        $state->fired = true;
    }

    public function apply(HooksState $state): void
    {
        $state->apply = true;
    }

    public function handle(HooksState $state): void
    {
        $state->handle = true;
    }
}
