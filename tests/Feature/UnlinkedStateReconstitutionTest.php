<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateManager;

/*
 * A state that apply() loads but that isn't linked to the event falls outside
 * the reconstitution component. A rebuild must read its live value (as a
 * normal apply() would), and must never leak a blank copy into the live scope.
 */

beforeEach(function () {
    UnlinkedRateEvent::fire(rate_id: 2, rate: 5);
    UnlinkedAccrueEvent::fire(total_id: 1, rate_id: 2);
    Verbs::commit();

    VerbSnapshot::query()->where('state_id', 1)->delete();
    app(StateManager::class)->reset();
});

it('rebuilds with the live value of a state outside the component', function () {
    expect(UnlinkedTotalState::load(1)->total)->toBe(5);
});

it('does not leak a blank copy of an unlinked state into the live scope', function () {
    UnlinkedTotalState::load(1);

    $rate = UnlinkedRateState::load(2);

    expect($rate->rate)->toBe(5)
        ->and($rate->last_event_id)->not->toBeNull();
});

class UnlinkedRateState extends State
{
    public int $rate = 0;
}

class UnlinkedTotalState extends State
{
    public int $total = 0;
}

class UnlinkedRateEvent extends Event
{
    #[StateId(UnlinkedRateState::class)]
    public int $rate_id;

    public int $rate;

    public function apply(UnlinkedRateState $state): void
    {
        $state->rate = $this->rate;
    }
}

class UnlinkedAccrueEvent extends Event
{
    #[StateId(UnlinkedTotalState::class)]
    public int $total_id;

    public int $rate_id;

    public function apply(UnlinkedTotalState $state): void
    {
        $state->total += UnlinkedRateState::load($this->rate_id)->rate;
    }
}
