<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\State;

it('only stores one snapshot per state', function () {
    $state1_id = snowflake_id();
    $state2_id = snowflake_id();

    DatabaseSnapshotTestEvent::fire(message: 'state 1, message 1', sid: $state1_id);
    DatabaseSnapshotTestEvent::fire(message: 'state 1, message 2', sid: $state1_id);
    DatabaseSnapshotTestEvent::fire(message: 'state 2, message 1', sid: $state2_id);
    DatabaseSnapshotTestEvent::fire(message: 'state 2, message 2', sid: $state2_id);

    Verbs::commit();

    $snapshots = VerbSnapshot::all();

    expect($snapshots)->toHaveCount(2)
        ->and($snapshots->firstWhere('state_id', $state1_id)->state()->last_message)->toBe('state 1, message 2')
        ->and($snapshots->firstWhere('state_id', $state2_id)->state()->last_message)->toBe('state 2, message 2');

    DatabaseSnapshotTestEvent::fire(message: 'state 1, message 3', sid: $state1_id);
    DatabaseSnapshotTestEvent::fire(message: 'state 2, message 3', sid: $state2_id);

    Verbs::commit();

    $snapshots = VerbSnapshot::all();

    expect($snapshots)->toHaveCount(2)
        ->and($snapshots->firstWhere('state_id', $state1_id)->state()->last_message)->toBe('state 1, message 3')
        ->and($snapshots->firstWhere('state_id', $state2_id)->state()->last_message)->toBe('state 2, message 3');
});

class DatabaseSnapshotTestEvent extends Event
{
    public function __construct(
        public string $message,
        #[StateId(DatabaseSnapshotTestState::class)] public ?int $sid,
    ) {}

    public function apply(DatabaseSnapshotTestState $state)
    {
        $state->last_message = $this->message;
    }
}

class DatabaseSnapshotTestState extends State
{
    public string $last_message = '';
}
