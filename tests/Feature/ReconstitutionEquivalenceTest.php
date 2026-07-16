<?php

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateManager;

/*
 * Property-based equivalence: for randomized event graphs (multi-state
 * events, singleton participation, cross-reading applies) and randomized
 * snapshot manipulations (rewound to an earlier point, partially rewound,
 * partially deleted), a reload must always reproduce the exact values the
 * full history produced—whether the engine picked the seeded window or the
 * blank baseline for any given load.
 */

test('reconstitution reproduces full-history values under randomized snapshot manipulation', function (int $case) {
    mt_srand($case * 9973);

    $a_ids = [snowflake_id(), snowflake_id()];
    $b_id = snowflake_id();

    $tracked = function () use ($a_ids, $b_id) {
        return [
            "a:{$a_ids[0]}" => EquivalenceStateA::load($a_ids[0]),
            "a:{$a_ids[1]}" => EquivalenceStateA::load($a_ids[1]),
            "b:{$b_id}" => EquivalenceStateB::load($b_id),
            'singleton' => EquivalenceSingleton::singleton(),
        ];
    };

    // Fire a random event graph, capturing every state's value and its own
    // last-touched event after each step (checkpoints for later rewinds).
    $checkpoints = [];

    foreach (range(1, 12) as $step) {
        $event = match (mt_rand(0, 4)) {
            0 => EquivalenceTouchA::fire(a_id: $a_ids[mt_rand(0, 1)], amount: mt_rand(1, 9)),
            1 => EquivalenceTouchB::fire(b_id: $b_id, amount: mt_rand(1, 9)),
            2 => EquivalenceCrossAB::fire(a_id: $a_ids[mt_rand(0, 1)], b_id: $b_id),
            3 => EquivalenceTouchSingleton::fire(a_id: $a_ids[mt_rand(0, 1)]),
            4 => EquivalenceCrossAll::fire(a_id: $a_ids[mt_rand(0, 1)], b_id: $b_id),
        };

        $checkpoints[$step] = collect($tracked())->map(fn (State $state) => [
            'value' => $state->value,
            'position' => $state->last_event_id,
        ])->all();
    }

    Verbs::commit();

    $truth = collect($tracked())->map(fn (State $state) => $state->value)->all();

    // Randomly manipulate the snapshots: rewind every state (or a random
    // subset) to a random checkpoint, and/or delete some snapshots entirely.
    $checkpoint = $checkpoints[mt_rand(4, 12)];
    $rewind_all = mt_rand(0, 1) === 1;

    $manipulate = function (string $key, string $type, int|string|null $state_id) use ($checkpoint, $rewind_all) {
        $query = VerbSnapshot::query()->where('type', $type);

        if ($state_id !== null) {
            $query->where('state_id', $state_id);
        }

        match (true) {
            mt_rand(0, 5) === 0 => $query->delete(),
            $rewind_all || mt_rand(0, 1) === 1 => $query->update([
                'data' => json_encode(['value' => $checkpoint[$key]['value']]),
                'last_event_id' => $checkpoint[$key]['position'],
            ]),
            default => null,
        };
    };

    $manipulate("a:{$a_ids[0]}", EquivalenceStateA::class, $a_ids[0]);
    $manipulate("a:{$a_ids[1]}", EquivalenceStateA::class, $a_ids[1]);
    $manipulate("b:{$b_id}", EquivalenceStateB::class, $b_id);
    $manipulate('singleton', EquivalenceSingleton::class, null);

    // A fresh scope must reproduce the exact full-history values.
    app(StateManager::class)->reset();

    foreach ($tracked() as $key => $state) {
        expect($state->value)->toBe($truth[$key], "State [{$key}] diverged in case [{$case}]");
    }
})->with(range(1, 25));

class EquivalenceStateA extends State
{
    public int $value = 0;
}

class EquivalenceStateB extends State
{
    public int $value = 0;
}

class EquivalenceSingleton extends SingletonState
{
    public int $value = 0;
}

class EquivalenceTouchA extends Event
{
    #[StateId(EquivalenceStateA::class)]
    public int $a_id;

    public int $amount;

    public function apply(EquivalenceStateA $a): void
    {
        $a->value = $a->value * 2 + $this->amount;
    }
}

class EquivalenceTouchB extends Event
{
    #[StateId(EquivalenceStateB::class)]
    public int $b_id;

    public int $amount;

    public function apply(EquivalenceStateB $b): void
    {
        $b->value = $b->value * 3 + $this->amount;
    }
}

class EquivalenceCrossAB extends Event
{
    #[StateId(EquivalenceStateA::class)]
    public int $a_id;

    #[StateId(EquivalenceStateB::class)]
    public int $b_id;

    public function apply(EquivalenceStateA $a, EquivalenceStateB $b): void
    {
        $a->value = $a->value * 2 + $b->value + 1;
        $b->value = $b->value * 2 + $a->value;
    }
}

#[AppliesToState(EquivalenceSingleton::class)]
class EquivalenceTouchSingleton extends Event
{
    #[StateId(EquivalenceStateA::class)]
    public int $a_id;

    public function apply(EquivalenceSingleton $singleton, EquivalenceStateA $a): void
    {
        $singleton->value = $singleton->value * 2 + $a->value + 1;
    }
}

#[AppliesToState(EquivalenceSingleton::class)]
class EquivalenceCrossAll extends Event
{
    #[StateId(EquivalenceStateA::class)]
    public int $a_id;

    #[StateId(EquivalenceStateB::class)]
    public int $b_id;

    public function apply(EquivalenceStateA $a, EquivalenceStateB $b, EquivalenceSingleton $singleton): void
    {
        $a->value = $a->value + $b->value + $singleton->value + 1;
        $b->value = $b->value * 2 + $a->value;
        $singleton->value = $singleton->value + $a->value;
    }
}
