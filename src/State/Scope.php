<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\Contracts\ReadableCache;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;
use Thunk\Verbs\Support\StateCollection;

class Scope
{
    public bool $replaying = false;

    public function __construct(
        public ReadableCache&WritableCache $cache,
    ) {}

    /**
     * Run the given callback with this scope bound as the "current" scope, so
     * that any states loaded during the callback resolve against it. The prior
     * scope is restored afterward, even if the callback throws. Because each
     * call captures the scope it replaced, nesting is handled by the call
     * stack—no separate scope manager is required.
     */
    public function run(callable $callback): mixed
    {
        $previous = app(Scope::class);

        try {
            app()->instance(Scope::class, $this);

            return $callback();
        } finally {
            app()->instance(Scope::class, $previous);
        }
    }

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
            : $this->loadOne($type, $id);
    }

    /**
     * @template TState of State
     *
     * @param  class-string<TState>  $type
     */
    public function singleton(string $type): State
    {
        return $this->load($type, null);
    }

    /**
     * @template TState of State
     *
     * @param  class-string<TState>  $type
     */
    public function make(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): State
    {
        // If we've already instantiated this state, we'll load it
        if ($existing = $this->cache->get($type, $id)) {
            return $existing;
        }

        // State::__construct() auto-registers the state with the Scope,
        // so we need to skip the constructor until we've already set the ID.
        /** @var State $state */
        $state = (new ReflectionClass($type))->newInstanceWithoutConstructor();
        $state->id = Id::tryFrom($id) ?? snowflake_id();
        $state->__construct();

        return $this->cache->put($state);
    }

    public function setReplaying(bool $replaying): static
    {
        $this->replaying = $replaying;

        return $this;
    }

    /** @return State[] */
    public function all(): array
    {
        return array_values($this->cache->values());
    }

    public function willPrune(): bool
    {
        return $this->cache->willPrune();
    }

    public function prune(): static
    {
        $this->cache->prune();

        return $this;
    }

    public function reset(): static
    {
        $this->cache->reset();
        $this->replaying = false;

        return $this;
    }

    /** @param  class-string<State>  $type */
    protected function loadOne(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id = null): State
    {
        $id = Id::tryFrom($id);

        if ($state = $this->cache->get($type, $id)) {
            return $state;
        }

        return $this->make($type, $id);
    }

    /** @param  class-string<State>  $type */
    protected function loadMany(iterable $ids, string $type): StateCollection
    {
        $ids = collect($ids)->map(Id::from(...));

        return StateCollection::make(
            // @todo - add support for getMany() in caches for perf
            $ids->map(fn ($id) => $this->loadOne($type, $id)),
        );
    }
}
