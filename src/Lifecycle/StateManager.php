<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use LogicException;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\EventStateRegistry;
use Thunk\Verbs\Support\StateCollection;
use Thunk\Verbs\Support\StateInstanceCache;
use UnexpectedValueException;

class StateManager
{
    protected bool $is_reconstituting = false;

    protected bool $is_replaying = false;

    public function __construct(
        protected Dispatcher $dispatcher,
        protected StoresSnapshots $snapshots,
        protected StoresEvents $events,
        protected StateInstanceCache $states,
    ) {}

    public function register(State $state): State
    {
        $state->id ??= snowflake_id();

        return $this->remember($state);
    }

    /**
     * @template S instanceof State
     *
     * @param  class-string<S>  $type
     * @return S|StateCollection<int,S>
     */
    public function load(Bits|UuidInterface|AbstractUid|iterable|int|string $id, string $type): StateCollection|State
    {
        // FIXME: This was not written to support loading multiple states
        // $summary = $this->events->summarize($state);
        // if ($summary->out_of_sync) {
        //     $this->snapshots->delete(...$summary->related_state_ids);
        // }

        return is_iterable($id)
            ? $this->loadMany($id, $type)
            : $this->loadOne($id, $type);
    }

    /**
     * @template TStateClass of State
     *
     * @param  class-string<TStateClass>  $type
     * @return TStateClass
     */
    public function singleton(string $type): State
    {
        // FIXME: If the state we're loading has a last_event_id that's ahead of the registry's last_event_id, we need to re-build the state

        if ($state = $this->states->get($type)) {
            return $state;
        }

        $state = $this->snapshots->loadSingleton($type) ?? new $type;
        $state->id ??= snowflake_id();

        // We'll store a reference to it by the type for future singleton access
        $this->states->put($type, $state);
        $this->remember($state);

        $this->reconstitute($state);

        return $state;
    }

    /**
     * @template TState of State
     *
     * @param  class-string<TState>  $type
     * @return TState
     */
    public function make(Bits|UuidInterface|AbstractUid|int|string $id, string $type): State
    {
        // If we've already instantiated this state, we'll load it
        if ($existing = $this->states->get($this->key($id, $type))) {
            return $existing;
        }

        // State::__construct() auto-registers the state with the StateManager,
        // so we need to skip the constructor until we've already set the ID.
        $state = (new ReflectionClass($type))->newInstanceWithoutConstructor();
        $state->id = Id::from($id);
        $state->__construct();

        return $this->remember($state);
    }

    public function writeSnapshots(): bool
    {
        return $this->snapshots->write($this->states->values());
    }

    public function setReplaying(bool $replaying): static
    {
        $this->is_replaying = $replaying;

        return $this;
    }

    public function reset(bool $include_storage = false): static
    {
        $this->states->reset();
        app(EventStateRegistry::class)->reset();

        $this->is_replaying = false;

        if ($include_storage) {
            $this->snapshots->reset();
        }

        return $this;
    }

    public function willPrune(): bool
    {
        return $this->states->willPrune();
    }

    public function prune(): static
    {
        $this->states->prune();

        return $this;
    }

    /** @param  class-string<State>  $type */
    protected function loadOne(Bits|UuidInterface|AbstractUid|int|string $id, string $type): State
    {
        $id = Id::from($id);
        $key = $this->key($id, $type);

        // FIXME: If the state we're loading has a last_event_id that's ahead of the registry's last_event_id, we need to re-build the state

        if ($state = $this->states->get($key)) {
            return $state;
        }

        if ($state = $this->snapshots->load($id, $type)) {
            if (! $state instanceof $type) {
                throw new UnexpectedValueException(sprintf('Expected State <%d> to be of type "%s" but got "%s"', $id, class_basename($type), class_basename($state)));
            }
        } else {
            $state = $this->make($id, $type);
        }

        $this->remember($state);
        $this->reconstitute($state);

        return $this->states->get($key); // FIXME
    }

    /** @param  class-string<State>  $type */
    protected function loadMany(iterable $ids, string $type): StateCollection
    {
        $ids = collect($ids)->map(Id::from(...));

        $missing = $ids->reject(fn ($id) => $this->states->has($this->key($id, $type)));

        // Load all available snapshots for missing states
        $this->snapshots->load($missing, $type)->each(function (State $state) {
            $this->remember($state);
            $this->reconstitute($state);
        });

        // Then make any states that don't exist yet
        $missing
            ->reject(fn ($id) => $this->states->has($this->key($id, $type)))
            ->each(function (string|int $id) use ($type) {
                $state = $this->make($id, $type);
                $this->remember($state);
                $this->reconstitute($state);
            });

        // At this point, all the states should be in our cache, so we can just load everything
        return StateCollection::make(
            $ids->map(fn ($id) => $this->states->get($this->key($id, $type))),
        );
    }

    protected function reconstitute(State $state): static
    {
        // FIXME: Only run this if the state is out of date
        if (! $this->needsReconstituting($state)) {
            // dump('skipping: everything in sync');
            return $this;
        }

        if (! $this->is_replaying && ! $this->is_reconstituting) {
            $real_registry = app(EventStateRegistry::class);

            try {
                $this->is_reconstituting = true;

                $summary = $this->events->summarize($state);

                [$temp_manager] = $this->bindNewEmptyStateManager();

                $this->events
                    ->get($summary->related_event_ids)
                    ->each($this->dispatcher->apply(...));

                foreach ($temp_manager->states->all() as $key => $state) {
                    $this->states->put($key, $state);
                }

            } finally {
                $this->is_reconstituting = false;

                app()->instance(StateManager::class, $this);
                app()->instance(EventStateRegistry::class, $real_registry);
            }
        }

        return $this;
    }

    protected function needsReconstituting(State $state): bool
    {
        $max_id = VerbStateEvent::query()
            ->where('state_id', $state->id)
            ->where('state_type', $state::class)
            ->max('event_id');

        return $max_id !== $state->last_event_id;
    }

    protected function bindNewEmptyStateManager()
    {
        $temp_manager = new StateManager(
            dispatcher: $this->dispatcher,
            snapshots: new NullSnapshotStore,
            events: $this->events,
            states: new StateInstanceCache,
        );
        $temp_manager->is_reconstituting = true; // FIXME

        $temp_registry = new EventStateRegistry($temp_manager);

        app()->instance(StateManager::class, $temp_manager);
        app()->instance(EventStateRegistry::class, $temp_registry);

        return [$temp_manager, $temp_registry];
    }

    protected function remember(State $state): State
    {
        $key = $this->key($state->id, $state::class);

        if ($this->states->get($key) === $state) {
            return $state;
        }

        if ($this->states->has($key)) {
            throw new LogicException('Trying to remember state twice.');
        }

        $this->states->put($key, $state);

        return $state;
    }

    protected function key(string|int $id, string $type): string
    {
        return "{$type}:{$id}";
    }
}
