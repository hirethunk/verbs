<?php

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;

it('verifies that snapshots match their events', function () {
    VerifyCommandTestEvent::fire(state_id: snowflake_id(), amount: 3);
    VerifyCommandTestEvent::fire(state_id: snowflake_id(), amount: 5);
    VerifyCommandTestSingletonEvent::fire();
    Verbs::commit();

    $this->artisan('verbs:verify')
        ->expectsOutputToContain('All verified snapshots match their events.')
        ->assertExitCode(0);
});

it('detects a snapshot that drifted from its events', function () {
    $id = snowflake_id();

    VerifyCommandTestEvent::fire(state_id: $id, amount: 3);
    VerifyCommandTestEvent::fire(state_id: $id, amount: 5);
    Verbs::commit();

    VerbSnapshot::query()->where('state_id', $id)->update(['data' => '{"total":999}']);

    $this->artisan('verbs:verify')
        ->expectsOutputToContain('does not match its events')
        ->assertExitCode(1);
});

it('verifies a stale-but-consistent snapshot against its own last_event_id', function () {
    $id = snowflake_id();

    $first = VerifyCommandTestEvent::fire(state_id: $id, amount: 3);
    Verbs::commit();

    // A second event arrives, but the snapshot legitimately lags at the
    // first event—verify must compare against the snapshot last_event_id, not head.
    VerifyCommandTestEvent::fire(state_id: $id, amount: 5);
    Verbs::commit();

    VerbSnapshot::query()->where('state_id', $id)->update([
        'data' => '{"total":3}',
        'last_event_id' => $first->id,
    ]);

    $this->artisan('verbs:verify')->assertExitCode(0);
});

it('can filter by type and id', function () {
    $good = snowflake_id();
    $bad = snowflake_id();

    VerifyCommandTestEvent::fire(state_id: $good, amount: 1);
    VerifyCommandTestEvent::fire(state_id: $bad, amount: 1);
    Verbs::commit();

    VerbSnapshot::query()->where('state_id', $bad)->update(['data' => '{"total":42}']);

    $this->artisan('verbs:verify', ['--id' => $good])->assertExitCode(0);
    $this->artisan('verbs:verify', ['--id' => $bad])->assertExitCode(1);
    $this->artisan('verbs:verify', ['--type' => VerifyCommandTestState::class])->assertExitCode(1);
});

it('reports when there is nothing to verify', function () {
    $this->artisan('verbs:verify')
        ->expectsOutputToContain('No snapshots to verify.')
        ->assertExitCode(0);
});

it('does not run unlessReplaying side effects while verifying', function () {
    $GLOBALS['verify_guard_side_effects'] = 0;

    VerifyGuardEvent::fire(state_id: snowflake_id());
    Verbs::commit();

    expect($GLOBALS['verify_guard_side_effects'])->toBe(1);

    $this->artisan('verbs:verify')->assertExitCode(0);

    expect($GLOBALS['verify_guard_side_effects'])->toBe(1);
});

class VerifyCommandTestState extends State
{
    public int $total = 0;
}

class VerifyCommandTestSingleton extends SingletonState
{
    public int $count = 0;
}

class VerifyCommandTestEvent extends Event
{
    #[StateId(VerifyCommandTestState::class)]
    public int $state_id;

    public int $amount;

    public function apply(VerifyCommandTestState $state): void
    {
        $state->total = $state->total * 2 + $this->amount;
    }
}

#[AppliesToState(VerifyCommandTestSingleton::class)]
class VerifyCommandTestSingletonEvent extends Event
{
    public function apply(VerifyCommandTestSingleton $singleton): void
    {
        $singleton->count++;
    }
}

class VerifyGuardState extends State
{
    public int $count = 0;
}

class VerifyGuardEvent extends Event
{
    #[StateId(VerifyGuardState::class)]
    public int $state_id;

    public function apply(VerifyGuardState $state): void
    {
        $state->count++;

        Verbs::unlessReplaying(fn () => $GLOBALS['verify_guard_side_effects']++);
    }
}
