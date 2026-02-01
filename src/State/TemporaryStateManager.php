<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\TracksState;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateCollection;

class TemporaryStateManager implements TracksState
{
    use LooksUpStateByKey;

    public function __construct(
        public Collection $states = new Collection
    ) {}

    public function register(State $state): State
    {
        $this->states->put($this->key($state), $state);

        return $state;
    }

    public function load(iterable|UuidInterface|string|int|AbstractUid|Bits $id, string $type): StateCollection|State
    {
        return is_iterable($id)
            ? $this->loadMany($id, $type)
            : $this->loadOne(Id::from($id), $type);
    }

    public function make(UuidInterface|string|int|AbstractUid|Bits $id, string $type): State
    {
        // If we've already instantiated this state, we'll load it
        if ($existing = $this->states->get($this->key($type, $id))) {
            return $existing;
        }

        // State::__construct() auto-registers the state with the StateManager,
        // so we need to skip the constructor until we've already set the ID.
        $state = (new ReflectionClass($type))->newInstanceWithoutConstructor();
        $state->id = Id::from($id);
        $state->__construct();

        $this->states->put($this->key($state), $state);

        return $state;
    }

    public function singleton(string $type): State
    {
        $key = $this->key($type);

        if ($this->states->has($key)) {
            return $this->states->get($key);
        }

        $state = $this->make(snowflake_id(), $type);
        $this->states->put($key, $state);

        return $state;
    }

    public function prune(): static
    {
        $this->states = new Collection;

        return $this;
    }

    /** @param  class-string<State>  $type */
    protected function loadOne(int|string $id, string $type): State
    {
        $key = $this->key($type, $id);

        if ($state = $this->states->get($key)) {
            return $state;
        }

        $state = $this->make($id, $type);

        $this->states->put($key, $state);

        return $state;
    }

    /** @param  class-string<State>  $type */
    protected function loadMany(iterable $ids, string $type): StateCollection
    {
        return StateCollection::make($ids)
            ->map(fn ($id) => $this->loadOne(Id::from($id), $type));
    }
}
