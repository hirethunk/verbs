<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\Contracts\ReadableCache;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;
use Thunk\Verbs\State\Cache\InMemoryCache;
use Thunk\Verbs\Support\EventStateRegistry;
use Thunk\Verbs\Support\StateCollection;

class StateManager
{
    public function __construct(
        public ReadableCache&WritableCache $cache,
        public StateResolver $resolver,
    ) {}

    /**
     * The isolated scope a rebuild replays into: blank-on-miss, and never
     * pruned (evicting a state mid-rebuild would reload it blank and corrupt
     * the replay)—its real memory bound is the size of the window being
     * replayed. Built with no container involvement, which is what keeps the
     * old Broker↔StateManager circular dependency from coming back.
     */
    public static function rebuilding(): static
    {
        return new static(new InMemoryCache(capacity: null), new RebuildResolver);
    }

    public function isReapplyingHistory(): bool
    {
        return $this->resolver instanceof ReappliesHistory;
    }

    /**
     * Run the given callback with this scope's resolver temporarily swapped
     * (e.g. Broker::replay() entering a ReplayResolver), with the same
     * save/restore discipline as run(), so nested swaps and mid-callback
     * throws always restore the previous policy.
     */
    public function withResolver(StateResolver $resolver, callable $callback): mixed
    {
        $previous = $this->resolver;

        try {
            $this->resolver = $resolver;

            return $callback();
        } finally {
            $this->resolver = $previous;
        }
    }

    /**
     * Run the given callback with this scope bound as the "current" scope, so
     * that any states loaded during the callback resolve against it. The prior
     * scope is restored afterward, even if the callback throws. Because each
     * call captures the scope it replaced, nesting is handled by the call
     * stack—no separate scope manager is required.
     */
    public function run(callable $callback): mixed
    {
        $previous = app(StateManager::class);

        try {
            app()->instance(StateManager::class, $this);

            return $callback();
        } finally {
            app()->instance(StateManager::class, $previous);
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
        [$type, $id] = $this->normalizeLoadArguments($type, $id);

        return is_iterable($id)
            ? $this->loadMany($type, $id)
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
        [$type, $id] = $this->normalizeLoadArguments($type, $id);

        // If we've already instantiated this state, we'll load it
        if ($existing = $this->cache->get($type, $this->cacheId($type, $id))) {
            return $existing;
        }

        $resolved_id = Id::tryFrom($id);

        // A null id is only meaningful for singletons (there is exactly one of
        // them). For a keyed state it almost always signals an accidental null
        // key—a missing route binding, a null foreign key—so we fail loudly
        // rather than silently minting an orphan state under a random id.
        if ($resolved_id === null && ! is_a($type, SingletonState::class, true)) {
            throw new InvalidArgumentException("Cannot load a [{$type}] state without an id.");
        }

        // State::__construct() auto-registers the state with the StateManager,
        // so we need to skip the constructor until we've already set the ID.
        /** @var State $state */
        $state = (new ReflectionClass($type))->newInstanceWithoutConstructor();
        $state->id = $resolved_id ?? snowflake_id();
        $state->__construct();

        return $this->cache->put($state);
    }

    /**
     * Bring a state up to date and return the *same instance*. Cache hits are
     * request-stable by design—within a request you compute against one
     * consistent view of each state—so refresh() is the explicit "ask
     * otherwise": it always runs the resolver's staleness check, re-adopts the
     * instance as canonical if the cache lost track of it (seeded from
     * storage, when the resolver has any), and syncs it from whichever
     * instance is canonical if the two ever diverged.
     */
    public function refresh(State $state): State
    {
        $canonical = $this->cache->get($state::class, $this->cacheId($state));

        if ($canonical === null) {
            // A state with queued-but-uncommitted events keeps its in-memory
            // view: seeding it from the (necessarily older) snapshot would
            // silently revert applies still on their way to storage.
            if (! $this->resolver->hasUncommittedEvents($state) && ($seed = $this->resolver->seedFor($state))) {
                $this->merge($seed, $state);
            }

            $canonical = $this->cache->put($state);
        }

        $this->resolver->reconcile($this, collect([$canonical]));

        if ($canonical !== $state) {
            if ($this->resolver->hasUncommittedEvents($state)) {
                // The caller's instance carries queued-but-uncommitted applies
                // that the canonical instance doesn't—syncing would silently
                // revert them (same rule as reconcile's harvest).
                Log::debug('Verbs: skipped syncing a state with uncommitted events from its canonical instance.', [
                    'state_type' => $state::class,
                    'state_id' => $state->id,
                ]);
            } else {
                // Another instance owns this identity (the cache was reset and
                // the identity reloaded behind this reference). Sync the
                // caller's instance from it rather than ever throwing.
                $this->merge($canonical, $state);

                Log::debug('Verbs: refreshed a state whose identity is now owned by a different instance.', [
                    'state_type' => $state::class,
                    'state_id' => $state->id,
                ]);
            }
        }

        return $state;
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

    /**
     * Protect a state from eviction (prune) while it has queued-but-uncommitted
     * events—reloading it as a fresh instance mid-batch would fork its identity.
     */
    public function pin(State $state): static
    {
        $this->cache->pin($state);

        return $this;
    }

    public function unpin(State $state): static
    {
        $this->cache->unpin($state);

        return $this;
    }

    public function reset(bool $include_storage = false): static
    {
        $this->cache->reset();

        // The registry caches the state instances it resolved for each event;
        // once we've cleared the cache those instances belong to a scope that no
        // longer exists, so we invalidate them in lockstep.
        app(EventStateRegistry::class)->reset();

        if ($include_storage) {
            trigger_error(
                'Passing $include_storage to StateManager::reset() is deprecated — call app(StoresSnapshots::class)->reset() directly instead.',
                E_USER_DEPRECATED,
            );

            app(StoresSnapshots::class)->reset();
        }

        return $this;
    }

    /**
     * Verbs 0.x took ($id, $type); load() and make() now take ($type, $id).
     * The old order is cheap to detect (the type is always a State class-string,
     * and an id never is), so we swap and warn for one release instead of breaking.
     */
    protected function normalizeLoadArguments(mixed $type, mixed $id): array
    {
        if (is_string($id) && is_a($id, State::class, true) && ! is_a((string) $type, State::class, true)) {
            trigger_error(
                'Passing ($id, $type) to StateManager::load()/make() is deprecated — pass ($type, $id) instead.',
                E_USER_DEPRECATED,
            );

            // An integer id was cast to a string on its way through the
            // string-typed $type parameter, so restore it as we swap back.
            return [$id, is_string($type) && ctype_digit($type) ? (int) $type : $type];
        }

        return [$type, $id];
    }

    /**
     * A singleton's cache identity is its type alone—its in-memory id is
     * incidental (and serialized event data may carry one), so lookups must
     * force it to null or they'd never match the one live instance.
     */
    public function cacheId(State|string $type, Bits|UuidInterface|AbstractUid|int|string|null $id = null): int|string|null
    {
        if ($type instanceof State) {
            [$type, $id] = [$type::class, $type->id];
        }

        return is_a($type, SingletonState::class, true) ? null : Id::tryFrom($id);
    }

    public function merge(State $from, State $into): void
    {
        foreach (get_object_vars($from) as $property => $value) {
            if ($property !== 'id') {
                $into->{$property} = $value;
            }
        }
    }

    /** @param  class-string<State>  $type */
    protected function loadOne(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id = null): State
    {
        if ($state = $this->cache->get($type, $this->cacheId($type, $id))) {
            return $state;
        }

        $state = $this->hydrateOne($type, $id);

        $this->resolver->reconcile($this, collect([$state]));

        return $state;
    }

    /** @param  class-string<State>  $type */
    protected function loadMany(string $type, iterable $ids): StateCollection
    {
        $ids = collect($ids);

        $missing = $ids
            ->filter(fn ($id) => ! $this->cache->has($type, $this->cacheId($type, $id)))
            ->values();

        if ($missing->isNotEmpty()) {
            $this->hydrateMisses($type, $missing);
        }

        $states = $ids->map(fn ($id) => $this->cache->get($type, $this->cacheId($type, $id)) ?? $this->make($type, $id));

        if ($missing->isNotEmpty()) {
            $this->resolver->reconcile($this, $states);
        }

        return StateCollection::make($states);
    }

    protected function hydrateOne(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): State
    {
        $hydrated = $this->resolver->hydrate($type, $id);

        return $hydrated === null
            ? $this->make($type, $id)
            : $this->cache->put($hydrated);
    }

    /**
     * Batch-hydrate a many-load's cache misses. Singletons never take the
     * batch path—their snapshot is keyed by type, not id—and ids that resolve
     * to nothing stay uncached, so the caller's make() fallback fails loudly
     * on an invalid key exactly as a single load would.
     *
     * @param  Collection<int, mixed>  $ids
     */
    protected function hydrateMisses(string $type, Collection $ids): void
    {
        if (is_a($type, SingletonState::class, true)) {
            foreach ($ids as $id) {
                if (! $this->cache->has($type, $this->cacheId($type, $id))) {
                    $this->hydrateOne($type, $id);
                }
            }

            return;
        }

        $resolvable = $ids
            ->map(fn ($id) => Id::tryFrom($id))
            ->filter(fn ($id) => $id !== null)
            ->unique()
            ->values();

        foreach ($this->resolver->hydrateMany($type, $resolvable) as $snapshot) {
            $this->cache->put($snapshot);
        }
    }
}
