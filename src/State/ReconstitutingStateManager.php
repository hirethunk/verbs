<?php

namespace Thunk\Verbs\State;

use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Lifecycle\ReplayMode;
use Thunk\Verbs\State\Cache\Contracts\ReadableCache;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;

/**
 * Construction shorthand for the request-bound scope: a StateManager whose
 * resolver is the snapshot-hydrating, reconstitute-on-stale policy. The
 * storage behavior that used to live here is now ReconstitutingResolver.
 */
class ReconstitutingStateManager extends StateManager
{
    public function __construct(
        StoresEvents $events,
        StoresSnapshots $snapshots,
        EventQueue $queue,
        ReplayMode $replay_mode,
        WritableCache&ReadableCache $cache,
    ) {
        parent::__construct($cache, new ReconstitutingResolver($events, $snapshots, $queue));
    }
}
