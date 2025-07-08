<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\Contracts\ReadableCache;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;
use Thunk\Verbs\Support\StateCollection;

class StateManager
{
    public function __construct(
        public ReadableCache&WritableCache $cache,
    ) {}

    public function register(State $state): State
    {
        $state->id ??= snowflake_id();

        return $this->cache->put($state);
    }

    /**
     * @template S instanceof State
     *
     * @param  class-string<S>  $type
     * @return S|StateCollection<int,S>
     */
    public function load(string $type, Bits|UuidInterface|AbstractUid|iterable|int|string|null $id): StateCollection|State
    {
        return is_iterable($id)
            ? $this->loadMany($id, $type)
            : $this->loadOne($id, $type);
    }

    /**
     * @template TState of State
     *
     * @param  class-string<TState>  $type
     * @return TState
     */
    public function make(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): State
    {
        // If we've already instantiated this state, we'll load it
        if ($existing = $this->cache->get($type, $id)) {
            return $existing;
        }

        // State::__construct() auto-registers the state with the StateManager,
        // so we need to skip the constructor until we've already set the ID.
        /** @var TState $state */
        $state = (new ReflectionClass($type))->newInstanceWithoutConstructor();
        $state->id = Id::tryFrom($id) ?? snowflake_id();
        $state->__construct();

        return $this->cache->put($state);
    }

    // @todo - make persistent caches
    // public function persist(): bool
    // {
    //     return $this->cache->persist($this->states->values());
    // }

    public function reset(): static
    {
        $this->cache->reset();

        return $this;
    }

    /** @param  class-string<State>  $type */
    protected function loadOne(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id = null): State
    {
        $id = Id::tryFrom($id);

        if ($state = $this->cache->get($type, $id)) {
            return $state;
        }

        return $this->make($id, $type);
    }

    /** @param  class-string<State>  $type */
    protected function loadMany(iterable $ids, string $type): StateCollection
    {
        $ids = collect($ids)->map(Id::from(...));

        return StateCollection::make(
            // @todo - add support for getMany() in caches for perf
            $ids->map(fn ($id) => $this->cache->get($type, $id)),
        );
    }
}
