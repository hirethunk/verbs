<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Lifecycle\AggregateStateSummary;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\Lifecycle\Phases;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\Contracts\ReadableCache;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;
use Thunk\Verbs\State\Cache\InMemoryCache;
use Thunk\Verbs\Support\Replay;
use Thunk\Verbs\Support\StateCollection;

class ReconstitutingStateManager extends StateManager
{
    public function __construct(
        protected StoresEvents $events,
        WritableCache&ReadableCache $cache,
    ) {
        parent::__construct($cache);
    }

    public function load(string $type, Bits|UuidInterface|AbstractUid|iterable|int|string|null $id): StateCollection|State
    {
        $states = parent::load($type, $id);

        if ($states instanceof State) {
            $states = new StateCollection([$states]);
        }

        // If there have been no events since ANY of these states' last_event_id, we can just return
        VerbStateEvent::query()
            ->toBase()
            ->select(['state_type', 'state_id', DB::raw('max(event_id) as max_event_id')])
            ->where(function ($query) use ($states) {
                foreach ($states as $state) {
                    $query->orWhere(function ($query) use ($state) {
                        $query->where('state_type', $state::class);
                        $query->where('state_id', $state->id);
                    });
                }
            })
            ->each(function ($row) {
                // TODO: Compare to states
            });

        $summary = AggregateStateSummary::summarize($states);

        $replay = new Replay(
            states: new StateManager(new InMemoryCache), // FIXME: Use states from summary
            events: $summary->events(),
            phases: new Phases(Phase::Apply),
        );

        $replay->handle();

        // FIXME: Get all states loaded during replay and add them to our cache

        // FIXME return $state;
    }
}
