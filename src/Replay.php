<?php

namespace Thunk\Verbs;

use Closure;
use Glhd\Bits\Bits;
use Illuminate\Support\Enumerable;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
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
use Thunk\Verbs\Support\Reflector;

/**
 * Drives an ordered stream of events through a bound StateManager "scope." This is
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
    /**
     * @param  Enumerable<int, Event>  $events
     * @param  Closure(Event): void  $drive
     *
     * @experimental
     */
    public function __construct(
        public StateManager $scope,
        protected Enumerable $events,
        protected Closure $drive,
        protected ?StateResolver $state_resolver = null,
        protected ?Closure $set_up = null,
        protected ?Closure $tear_down = null,
        protected ?Closure $before_each = null,
        protected ?Closure $after_each = null,
        protected ?Closure $checkpoint = null,
        protected ?Closure $result_mapper = null,
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

        return new static(
            scope: $scope,
            events: app(StoresEvents::class)->read(),
            drive: function (Event $event) use ($dispatcher) {
                $dispatcher->apply($event);
                $dispatcher->replay($event);
            },
            state_resolver: new ReplayResolver($snapshots),
            set_up: function () use ($queue, $scope, $snapshots) {
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
            },
            tear_down: function () use ($scope, $snapshots) {
                $scope->persistSnapshots($snapshots);
                $scope->prune();
            },
            checkpoint: function (int $iteration) use ($scope, $snapshots) {
                if ($iteration % 500 === 0 && $scope->willPrune()) {
                    $scope->persistSnapshots($snapshots);
                    $scope->prune();
                }
            },
        );
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

        if ($resolved_id === null && ! is_a($state, SingletonState::class, true)) {
            throw new InvalidArgumentException("Cannot rebuild a [{$state}] state without an id.");
        }

        // We need to instantiate the "shell" of the state without calling the constructor so that it
        // never auto-registers itself with the current state manager.
        $shell = Reflector::newInstanceWithoutConstructorWithPromotedDefaults($state);
        $shell->id = $resolved_id ?? snowflake_id();

        $scope = StateManager::rebuilding();

        return new static(
            scope: $scope,
            events: ReconstitutionPlan::plan(collect([$shell]), use_snapshots: false)->events(),
            drive: fn (Event $event) => Lifecycle::run($event, Phases::apply()),
            result_mapper: fn (StateManager $scope) => $scope->cache->get($state, $scope->cacheId($state, $id)) ?? $shell,
        );
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
        $this->before_each = $callback === null ? null : $callback(...);

        return $this;
    }

    /** @experimental */
    public function afterEach(?callable $callback): static
    {
        $this->after_each = $callback === null ? null : $callback(...);

        return $this;
    }

    /** @experimental */
    public function run(): mixed
    {
        // Current tear-down implementations assume setup succeeded, so if it fails we want to throw
        // outside the try/finally. We may want to consider renaming for clarity.
        $this->runCallback($this->set_up);

        try {
            // Run with our replay state manager as the current "scope" and optionally override the
            // manager's state resolver with the replay's resolver
            $count = $this->scope->run(fn () => $this->state_resolver
                ? $this->scope->withResolver($this->state_resolver, fn () => $this->loop())
                : $this->loop());

            return $this->runCallback($this->result_mapper, [$this->scope], $count);
        } finally {
            $this->runCallback($this->tear_down);
        }
    }

    protected function loop(): int
    {
        $iteration = 0;

        foreach ($this->events as $event) {
            $this->runCallback($this->before_each, [$event]);
            $this->runCallback($this->drive, [$event]);
            $this->runCallback($this->after_each, [$event]);
            $this->runCallback($this->checkpoint, [$iteration]);

            $iteration++;
        }

        return $iteration;
    }

    protected function runCallback(?Closure $callback, array $args = [], mixed $default = null): mixed
    {
        return $callback ? call_user_func_array($callback, $args) : $default;
    }
}
