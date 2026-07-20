<?php

namespace Thunk\Verbs;

use Closure;
use Illuminate\Support\Enumerable;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Exceptions\CannotReplayWithQueuedEvents;
use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Lifecycle\SnapshotWriter;
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
        $writer = new SnapshotWriter($snapshots, app(MetadataManager::class));

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
        $replay->checkpoint = function (int $iteration) use ($scope, $writer) {
            if ($iteration % 500 === 0 && $scope->willPrune()) {
                $writer->write($scope->all());
                $scope->prune();
            }
        };

        $replay->tear_down = function () use ($scope, $writer) {
            $writer->write($scope->all());
            $scope->prune();
        };

        return $replay;
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
