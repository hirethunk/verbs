<?php

namespace Thunk\Verbs\Support;

use Illuminate\Contracts\Container\Container;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\NullSnapshotStore;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateManager;

class StateReconstructor
{
    public function __construct(
        protected Container $container,
        protected Dispatcher $dispatcher,
        protected StoresEvents $events,
    ) {}

    public function handle(State $state, StateManager $manager): State
    {
        $original_manager = $this->container->make(StateManager::class);
        $reconstruction_manager = new StateManager(
            dispatcher: $this->dispatcher,
            snapshots: new NullSnapshotStore,
            events: $this->events,
            states: new StateInstanceCache,
        );

        $this->container->instance(StateManager::class, $reconstruction_manager);

        try {
            $summary = $this->events->summarize($state);

            $this->events
                ->get($summary->related_event_ids)
                ->each($this->dispatcher->apply(...));

            foreach ($reconstruction_manager->states() as $state) {
                $manager->push($state);
            }
        } finally {
            $this->container->instance(StateManager::class, $original_manager);
        }

        return $original_manager->load($state::class, $state->id);
    }

    protected function bindNewEmptyStateManager(StateManager $manager)
    {

        $temp_manager->is_reconstituting = true; // FIXME

        $temp_registry = new EventStateRegistry($temp_manager);

        app()->instance(StateManager::class, $temp_manager);
        app()->instance(EventStateRegistry::class, $temp_registry);

        return [$temp_manager, $temp_registry];
    }
}
