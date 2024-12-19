<?php

use Thunk\Verbs\Lifecycle\AggregateStateSummary;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateIdentity;

test('it finds the correct states and events for one state', function () {
    $matching_state_types = [
        AggregateStateSummaryTestState1::class,
        AggregateStateSummaryTestState2::class,
        AggregateStateSummaryTestState3::class,
    ];
    $matching_state_ids = [10, 11, 12, 13, 14];
    $matching_event_ids = [100, 101, 102, 103, 105];

    $other_state_types = [
        AggregateStateSummaryTestState4::class,
        AggregateStateSummaryTestState5::class,
        AggregateStateSummaryTestState6::class,
    ];
    $other_state_ids = [20, 21, 22, 23, 24];
    $other_event_ids = [200, 201, 202, 203, 205];

    foreach ($matching_state_ids as $state_index => $matching_state_id) {
        foreach ($matching_event_ids as $matching_event_id) {
            VerbStateEvent::insert([
                'id' => snowflake_id(),
                'event_id' => $matching_event_id,
                'state_id' => $matching_state_id,
                'state_type' => $matching_state_types[$state_index % count($matching_state_types)],
            ]);
        }
    }

    $target_state = new AggregateStateSummaryTestState1;
    $target_state->id = 10;

    $summary = AggregateStateSummary::summarize($target_state);

    expect($summary->original_states->all())->toBe([$target_state])
        ->and($summary->related_states)->toHaveCount(5)
        ->and($summary->related_event_ids)->toHaveCount(5);

    $related_state_ids = $summary->related_states
        ->map(fn (StateIdentity $state) => $state->state_id)
        ->sort()
        ->toArray();

    $related_state_types = $summary->related_states
        ->map(fn (StateIdentity $state) => $state->state_type)
        ->unique()
        ->sort()
        ->toArray();

    expect($related_state_ids)->toBe($matching_state_ids)
        ->and($related_state_types)->toBe($matching_state_types);
});

test('it finds the correct states and events for multiple states', function () {
    $matching_state_types = [
        AggregateStateSummaryTestState1::class,
        AggregateStateSummaryTestState2::class,
        AggregateStateSummaryTestState3::class,
    ];
    $matching_state_ids = [10, 11, 12, 13, 14];
    $matching_event_ids = [100, 101, 102, 103, 105];

    $other_state_types = [
        AggregateStateSummaryTestState4::class,
        AggregateStateSummaryTestState5::class,
        AggregateStateSummaryTestState6::class,
    ];
    $other_state_ids = [20, 21, 22, 23, 24];
    $other_event_ids = [200, 201, 202, 203, 205];

    foreach ($matching_state_ids as $state_index => $matching_state_id) {
        foreach ($matching_event_ids as $matching_event_id) {
            VerbStateEvent::insert([
                'id' => snowflake_id(),
                'event_id' => $matching_event_id,
                'state_id' => $matching_state_id,
                'state_type' => $matching_state_types[$state_index % count($matching_state_types)],
            ]);
        }
    }

    $target_state1 = new AggregateStateSummaryTestState1;
    $target_state1->id = 10;

    $target_state2 = new AggregateStateSummaryTestState2;
    $target_state2->id = 11;

    $summary = AggregateStateSummary::summarize($target_state1, $target_state2);

    expect($summary->original_states->all())->toBe([$target_state1, $target_state2])
        ->and($summary->related_states)->toHaveCount(5)
        ->and($summary->related_event_ids)->toHaveCount(5);

    $related_state_ids = $summary->related_states
        ->map(fn (StateIdentity $state) => $state->state_id)
        ->sort()
        ->toArray();

    $related_state_types = $summary->related_states
        ->map(fn (StateIdentity $state) => $state->state_type)
        ->unique()
        ->sort()
        ->toArray();

    expect($related_state_ids)->toBe($matching_state_ids)
        ->and($related_state_types)->toBe($matching_state_types);
});

class AggregateStateSummaryTestState1 extends State {}
class AggregateStateSummaryTestState2 extends State {}
class AggregateStateSummaryTestState3 extends State {}
class AggregateStateSummaryTestState4 extends State {}
class AggregateStateSummaryTestState5 extends State {}
class AggregateStateSummaryTestState6 extends State {}
