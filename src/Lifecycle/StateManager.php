<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use LogicException;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Id;
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

    /** @param  class-string<State>  $type */
    public function singleton(string $type): State
    {
        // FIXME: If the state we're loading has a last_event_id that's ahead of the registry's last_event_id, we need to re-build the state

        if ($state = $this->states->get($type)) {
            return $state;
        }

        $state = $this->snapshots->loadSingleton($type) ?? $type::make();
        $state->id ??= snowflake_id();

        // We'll store a reference to it by the type for future singleton access
        $this->states->put($type, $state);
        $this->remember($state);

        $this->reconstitute($state, singleton: true);

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

        return $state;
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
            $ids->map(fn ($id) => $this->states->get($this->key($id, $type)))
        );
    }

    protected function reconstitute(State $state, bool $singleton = false): static
    {
        // When we're replaying, the Broker is in charge of applying the correct events
        // to the State, so we need to skip during replays. Similarly, if we're already
        // reconstituting in a recursive call, the root call is responsible for applying
        // events, so we should also skip in that case.

        if (! $this->is_replaying && ! $this->is_reconstituting) {
            try {
                $this->is_reconstituting = true;

                $summary = $this->events->summarize($state, $singleton);

                // FIXME:
                if ($summary->out_of_sync) {
                    $this->snapshots->delete(...$summary->related_state_ids);
                }

                $this->events->get($summary->related_event_ids)
                    ->filter(function (Event $event) {
                        $last_event_ids = $event->states()
                            ->map(fn (State $state) => $state->last_event_id)
                            ->filter();

                        $min = $last_event_ids->min() ?? PHP_INT_MIN;
                        $max = $last_event_ids->max() ?? PHP_INT_MIN;

                        // If all states have had this or future events applied, just ignore them
                        if ($min >= $event->id && $max >= $event->id) {
                            return false;
                        }

                        // We should never be in a situation where some events are ahead and
                        // others are behind, so if that's the case we'll throw an exception
                        if ($max > $event->id && $min <= $event->id) {
                            throw new RuntimeException('Trying to apply an event to states that are out of sync.');
                        }

                        return true;
                    })
                    ->each($this->dispatcher->apply(...));

            } finally {
                $this->is_reconstituting = false;
            }
        }

        return $this;
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
