<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\CommitsImmediately;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\State;

/*
 * Broker::fire() must queue an event *before* running its fired() hooks, so
 * any child events fired from those hooks queue (and commit) behind their
 * parent. These pin that ordering.
 */

beforeEach(function () {
    FiredHookLog::reset();
});

it('commits a CommitsImmediately child fired from a fired() hook together with its parent', function () {
    FiredHookParentEvent::fire(parent_id: snowflake_id(), child_id: snowflake_id());

    expect(VerbEvent::query()->orderBy('id')->pluck('type')->all())
        ->toBe([FiredHookParentEvent::class, FiredHookChildEvent::class]);

    expect(FiredHookLog::$handled)->toBe(['parent', 'child']);
});

it('never persists a snapshot that references an unpersisted event', function () {
    $parent = FiredHookParentEvent::fire(parent_id: snowflake_id(), child_id: snowflake_id());

    $parent_snapshot = VerbSnapshot::query()->type(FiredHookParentState::class)->first();

    expect((int) $parent_snapshot->last_event_id)->toBe($parent->id);

    $event_ids = VerbEvent::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

    VerbSnapshot::query()->each(function (VerbSnapshot $snapshot) use ($event_ids) {
        expect($event_ids)->toContain((int) $snapshot->last_event_id);
    });
});

it('queues a nested fired() child behind its parent, keeping handler and pivot order aligned', function () {
    $outer = FiredHookOuterEvent::fire(outer_id: snowflake_id(), inner_id: snowflake_id());

    Verbs::commit();

    $inner = FiredHookLog::$inner_event;

    expect($outer->id)->toBeLessThan($inner->id);

    expect(FiredHookLog::$handled)->toBe(['outer', 'inner']);

    expect(VerbEvent::query()->orderBy('id')->pluck('type')->all())
        ->toBe([FiredHookOuterEvent::class, FiredHookInnerEvent::class]);

    $pivot_event_ids = VerbStateEvent::query()
        ->orderBy('id')
        ->pluck('event_id')
        ->map(fn ($id) => (int) $id)
        ->all();

    expect($pivot_event_ids)->toBe([$outer->id, $inner->id]);
});

class FiredHookLog
{
    public static array $handled = [];

    public static ?Event $inner_event = null;

    public static function reset(): void
    {
        static::$handled = [];
        static::$inner_event = null;
    }
}

class FiredHookParentState extends State
{
    public int $count = 0;
}

class FiredHookChildState extends State
{
    public int $count = 0;
}

class FiredHookParentEvent extends Event
{
    #[StateId(FiredHookParentState::class)]
    public int $parent_id;

    public int $child_id;

    public function apply(FiredHookParentState $state)
    {
        $state->count++;
    }

    public function fired()
    {
        FiredHookChildEvent::fire(child_id: $this->child_id);
    }

    public function handle()
    {
        FiredHookLog::$handled[] = 'parent';
    }
}

class FiredHookChildEvent extends Event implements CommitsImmediately
{
    #[StateId(FiredHookChildState::class)]
    public int $child_id;

    public function apply(FiredHookChildState $state)
    {
        $state->count++;
    }

    public function handle()
    {
        FiredHookLog::$handled[] = 'child';
    }
}

class FiredHookOuterEvent extends Event
{
    #[StateId(FiredHookParentState::class)]
    public int $outer_id;

    public int $inner_id;

    public function apply(FiredHookParentState $state)
    {
        $state->count++;
    }

    public function fired()
    {
        FiredHookLog::$inner_event = FiredHookInnerEvent::fire(inner_id: $this->inner_id);
    }

    public function handle()
    {
        FiredHookLog::$handled[] = 'outer';
    }
}

class FiredHookInnerEvent extends Event
{
    #[StateId(FiredHookChildState::class)]
    public int $inner_id;

    public function apply(FiredHookChildState $state)
    {
        $state->count++;
    }

    public function handle()
    {
        FiredHookLog::$handled[] = 'inner';
    }
}
