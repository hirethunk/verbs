<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Lifecycle\AggregateStateSummary;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\Contracts\ReadableCache;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;
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
        $state = parent::load($type, $id);

        $summary = AggregateStateSummary::summarize($state);

        /*
         * Scenarios we care about:
         *   - There are events that fired since these state(s) were loaded
         *   - We have an out-of-date snapshot
         *   - This state relies on other state that's out of date
         */

        // FIXME:
        // Figure out if state(s) is up-to-date
        // If not, set up a Replay and run it, then grab the states from
        // that replay and push them into this.

        return $state;
    }
}
