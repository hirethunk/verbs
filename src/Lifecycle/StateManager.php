<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use LogicException;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateCollection;
use Thunk\Verbs\Support\StateInstanceCache;
use UnexpectedValueException;

class StateManager
{
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

    /** @param  class-string<State>  $type */
    public function load(Bits|UuidInterface|AbstractUid|int|string|array|Collection $id, string $type): StateCollection|State
    {
        $ids = StateCollection::wrap($id);

        $states = $ids->map(fn ($id) => $this->loadOne($id, $type));

        return $states->count() === 1 ? $states->first() : $states;
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

    protected function reconstitute(State $state, bool $singleton = false): static
    {
        // When we're replaying, the Broker is in charge of applying the correct events
        // to the State, so we only need to do it *outside* of replays.
        if (! $this->is_replaying) {
            $this->events
                ->read(state: $state, after_id: $state->last_event_id, singleton: $singleton)
                ->each(fn (Event $event) => $this->dispatcher->apply($event));

            // It's possible for an event to mutate state out of order when reconstituting,
            // so as a precaution, we'll clear all other states from the store and reload
            // them from snapshots as needed in the rest of the request.
            // FIXME: We still need to figure this out
            // $this->states->reset();
            //$this->remember($state);
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
