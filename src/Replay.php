<?php

namespace Thunk\Verbs;

use Closure;
use Glhd\Bits\Bits;
use Illuminate\Support\Enumerable;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Exceptions\CannotReplayWithQueuedEvents;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\Lifecycle;
use Thunk\Verbs\Lifecycle\Phases;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\State\ReconstitutionPlan;
use Thunk\Verbs\State\ReplayResolver;
use Thunk\Verbs\State\StateManager;
use Thunk\Verbs\State\StateResolver;

/**
 * Drives an ordered stream of events through a bound StateManager scope. This is
 * the single shape the three replay sites share—full replay, reconstitution's
 * window rebuild, and verification's blank rebuild all *select events → bind a
 * scope → apply each event → post-process*. What differs between them is
 * composed in as closures (which events, what to do per event, what to do
 * after), never subclassed.
 *
 * The event stream is iterated lazily and never materialized (StreamingReplayTest
 * pins a flat-memory envelope through the checkpoint/prune cadence).
 *
 * @experimental
 */
class Replay
{
    /** @var null|Closure(Event): void */
    protected ?Closure $before = null;

    /** @var null|Closure(Event): void */
    protected ?Closure $after = null;

    /** @var null|Closure(int): void */
    protected ?Closure $checkpoint = null;

    /**
     * Swapped onto the scope for the duration of the drive (e.g. full replay's
     * ReplayResolver). Null when the scope already carries the resolver it needs
     * (the rebuild sub-scopes construct with a RebuildResolver).
     */
    protected ?StateResolver $resolver = null;

    /** @var null|Closure(): void one-time, before the loop and outside its try */
    protected ?Closure $set_up = null;

    /** @var null|Closure(): void one-time, in the finally */
    protected ?Closure $tear_down = null;

    /** @var null|Closure(StateManager): mixed computes run()'s return value */
    protected ?Closure $result = null;

    /**
     * @param  Enumerable<int, Event>  $events
     * @param  Closure(Event): void  $drive
     *
     * @experimental
     */
    public function __construct(
        public StateManager $scope,
        public Enumerable $events,
        public Closure $drive,
    ) {}

    /**
     * Full replay: re-run every stored event, in order, through the live scope
     * under a ReplayResolver, writing snapshots as it goes. A userland
     * Replay::full()->run() persists identically to Verbs::replay().
     *
     * @experimental
     */
    public static function full(): static
    {
        $scope = app(StateManager::class);
        $snapshots = app(StoresSnapshots::class);
        $queue = app(EventQueue::class);
        $dispatcher = app(Dispatcher::class);

        $replay = new static(
            scope: $scope,
            events: app(StoresEvents::class)->read(),
            drive: function (Event $event) use ($dispatcher) {
                $dispatcher->apply($event);
                $dispatcher->replay($event);
            },
        );

        $replay->resolver = new ReplayResolver($snapshots);

        $replay->set_up = function () use ($queue, $scope, $snapshots) {
            // A queued event has already applied to in-memory state but isn't
            // part of stored history yet: the replay would reset that state out
            // from under it, and a later commit would splice the event in on top
            // of the rebuilt world. Fail loudly rather than lose or double-apply.
            if ($queued = count($queue->getEvents())) {
                throw new CannotReplayWithQueuedEvents(sprintf(
                    'Cannot replay while %s queued but uncommitted—commit or discard them before replaying.',
                    $queued === 1 ? '1 event is' : "{$queued} events are",
                ));
            }

            $scope->reset();
            $snapshots->reset();
        };

        // Bound memory: the same 500-event cadence Broker::replay() used, gated
        // on willPrune() so a small replay never churns snapshots mid-stream.
        $replay->checkpoint = function (int $iteration) use ($scope, $snapshots) {
            if ($iteration % 500 === 0 && $scope->willPrune()) {
                $scope->persistSnapshots($snapshots);
                $scope->prune();
            }
        };

        $replay->tear_down = function () use ($scope, $snapshots) {
            $scope->persistSnapshots($snapshots);
            $scope->prune();
        };

        return $replay;
    }

    /**
     * Fresh rebuild: reconstruct a single state from a blank baseline by
     * replaying its connected component (optionally up to a ceiling via upTo())
     * inside a throwaway scope. Read-only—nothing is written and the live scope
     * is never touched—so run() returns the rebuilt State: an eventless rebuild
     * comes back as a blank shell with last_event_id === null.
     *
     * @experimental
     */
    public static function fresh(State|string $state, Bits|UuidInterface|AbstractUid|int|string|null $id = null): static
    {
        if ($state instanceof State) {
            [$state, $id] = [$state::class, $state->id];
        }

        $resolved_id = Id::tryFrom($id);

        // Mirror StateManager::make(): a null id is only meaningful for a
        // singleton (there is exactly one). A keyed rebuild without an id fails
        // loudly rather than rebuilding nothing under a random key.
        if ($resolved_id === null && ! is_a($state, SingletonState::class, true)) {
            throw new InvalidArgumentException("Cannot rebuild a [{$state}] state without an id.");
        }

        // A blank shell built without the constructor never auto-registers in
        // the live scope (State::__construct() would). It seeds the plan's
        // component discovery and doubles as the eventless return value. A
        // singleton's incidental id is irrelevant—its component is by type.
        $shell = (new ReflectionClass($state))->newInstanceWithoutConstructor();
        $shell->id = $resolved_id ?? snowflake_id();

        $plan = ReconstitutionPlan::plan(collect([$shell]), use_snapshots: false);

        $scope = StateManager::rebuilding();

        $replay = new static(
            scope: $scope,
            events: $plan->events(),
            drive: fn (Event $event) => Lifecycle::run($event, Phases::apply()),
        );

        $replay->result = fn (StateManager $scope) => $scope->cache->get($state, $scope->cacheId($state, $id)) ?? $shell;

        return $replay;
    }

    /**
     * Cap a fresh rebuild at an inclusive event-id ceiling (default: head), so a
     * state can be reconstructed as of a specific point in its history.
     *
     * @experimental
     */
    public function upTo(Event|Bits|UuidInterface|AbstractUid|int|string $ceiling): static
    {
        $ceiling = Id::from($ceiling instanceof Event ? $ceiling->id : $ceiling);

        // takeUntil stops before the first event past the ceiling—the lazy
        // equivalent of "break once id > ceiling", keeping the ceiling inclusive.
        $this->events = $this->events->takeUntil(fn (Event $event) => Id::from($event->id) > $ceiling);

        return $this;
    }

    /** @experimental */
    public function beforeEach(?callable $callback): static
    {
        $this->before = $callback === null ? null : Closure::fromCallable($callback);

        return $this;
    }

    /** @experimental */
    public function afterEach(?callable $callback): static
    {
        $this->after = $callback === null ? null : Closure::fromCallable($callback);

        return $this;
    }

    /** @experimental */
    public function run(): mixed
    {
        // The guard/reset live outside the try so a bailed run (queued events)
        // never triggers the tear-down's snapshot write.
        if ($this->set_up) {
            ($this->set_up)();
        }

        try {
            // run() binds this scope as the *current* scope for the duration, so
            // the readers of the re-applying signal (fire(), unlessReplaying())
            // and every state load agree with the resolver swap by construction.
            $count = $this->scope->run(fn () => $this->resolver
                ? $this->scope->withResolver($this->resolver, fn () => $this->loop())
                : $this->loop());

            return $this->result ? ($this->result)($this->scope) : $count;
        } finally {
            if ($this->tear_down) {
                ($this->tear_down)();
            }
        }
    }

    protected function loop(): int
    {
        $iteration = 0;

        foreach ($this->events as $event) {
            if ($this->before) {
                ($this->before)($event);
            }

            ($this->drive)($event);

            if ($this->after) {
                ($this->after)($event);
            }

            if ($this->checkpoint) {
                ($this->checkpoint)($iteration);
            }

            $iteration++;
        }

        return $iteration;
    }
}
