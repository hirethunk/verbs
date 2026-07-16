<?php

namespace Thunk\Verbs\Testing;

use Closure;
use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Traits\ReflectsClosures;
use PHPUnit\Framework\Assert as PHPUnit;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateIdentity;

class EventStoreFake implements StoresEvents
{
    use ReflectsClosures;

    /** @var Collection<int, Collection<int, Event>> */
    protected Collection $events;

    public function __construct(
        protected MetadataManager $metadata,
    ) {
        $this->events = new Collection;
    }

    public function read(
        ?State $state = null,
        UuidInterface|string|int|AbstractUid|Bits|null $after_id = null,
    ): LazyCollection {
        return LazyCollection::make($this->events)
            ->flatten()
            ->when($after_id, function (LazyCollection $events, $after_id) {
                return $events->filter(fn (Event $event) => $event->id > Id::from($after_id));
            })
            ->when($state, function (LazyCollection $events, State $state) {
                return $state instanceof SingletonState
                    ? $events->filter(fn (Event $event) => $event->state($state::class) !== null)
                    : $events->filter(fn (Event $event) => $event->state($state::class)?->id === $state->id);
            })
            ->values();
    }

    public function write(array $events): bool
    {
        foreach ($events as $event) {
            $this->events[$event::class] ??= new Collection;
            $this->events[$event::class]->push($event);
        }

        return true;
    }

    public function get(iterable $ids): LazyCollection
    {
        $ids = collect($ids)->map(Id::from(...))->flip();

        return LazyCollection::make(
            $this->events
                ->flatten()
                ->filter(fn (Event $event) => $ids->has($event->id))
                ->sortBy(fn (Event $event) => $event->id)
                ->values()
        );
    }

    public function hasEventsBeyondPositions(iterable $states): bool
    {
        return collect($states)->contains(function (StateIdentity $state) {
            $max = $this->eventsFor($state)->max(fn (Event $event) => $event->id);

            if (! $max) {
                return false;
            }

            return $max > ($state->position ? Id::from($state->position) : 0);
        });
    }

    public function hasEventsWithinPositions(iterable $states, int|string|null $after = null): bool
    {
        return collect($states)->contains(function (StateIdentity $state) use ($after) {
            return $state->position !== null && $this->eventsFor($state)->contains(
                fn (Event $event) => ($after === null || $event->id > $after) && $event->id <= $state->position,
            );
        });
    }

    public function eventIdsForStates(iterable $states, int|string|null $after = null): Collection
    {
        $states = collect($states);

        return $this->events
            ->flatten()
            ->filter(fn (Event $event) => $after === null || $event->id > $after)
            ->filter(fn (Event $event) => $states->contains(
                fn (StateIdentity $state) => $this->touches($event, $state),
            ))
            ->map(fn (Event $event) => $event->id)
            ->unique()
            ->values();
    }

    public function statesForEvents(iterable $event_ids): Collection
    {
        $ids = collect($event_ids)->map(Id::from(...))->flip();

        return $this->events
            ->flatten()
            ->filter(fn (Event $event) => $ids->has($event->id))
            ->flatMap(fn (Event $event) => $event->states()->map(StateIdentity::from(...)))
            ->unique(fn (StateIdentity $state) => $state->state_type.':'.$state->state_id)
            ->values();
    }

    /** @return Collection<int, Event> */
    public function committed(string $class_name, ?Closure $filter = null): Collection
    {
        if (! $this->hasCommitted($class_name)) {
            return new Collection;
        }

        return $this->events[$class_name]
            ->when($filter !== null, fn ($events) => $events->filter($filter))
            ->values();
    }

    public function hasCommitted($event): bool
    {
        return $this->events->has($event)
            && $this->events->get($event)->isNotEmpty();
    }

    public function assertCommitted(string|Closure $event, Closure|int|null $callback = null): static
    {
        if ($event instanceof Closure) {
            [$event, $callback] = [$this->firstClosureParameterType($event), $event];
        }

        if (is_int($callback)) {
            return $this->assertCommittedTimes($event, $callback);
        }

        PHPUnit::assertTrue(
            $this->committed($event, $callback)->count() > 0,
            "The expected [{$event}] event was not committed."
        );

        return $this;
    }

    public function assertNotCommitted(string|Closure $event, ?Closure $callback = null): static
    {
        if ($event instanceof Closure) {
            [$event, $callback] = [$this->firstClosureParameterType($event), $event];
        }

        PHPUnit::assertCount(
            0, $this->committed($event, $callback),
            "The unexpected [{$event}] event was committed."
        );

        return $this;
    }

    public function assertNothingCommitted(): static
    {
        PHPUnit::assertEmpty($this->events, 'Events were committed unexpectedly.');

        return $this;
    }

    protected function assertCommittedTimes(string $class_name, int $times = 1): static
    {
        $count = $this->committed($class_name)->count();

        PHPUnit::assertSame(
            expected: $times,
            actual: $count,
            message: "The expected [{$class_name}] event was committed {$count} times instead of {$times} times.",
        );

        return $this;
    }

    /** @return Collection<int, Event> */
    protected function eventsFor(StateIdentity $state): Collection
    {
        return $this->events
            ->flatten()
            ->filter(fn (Event $event) => $this->touches($event, $state))
            ->values();
    }

    protected function touches(Event $event, StateIdentity $state): bool
    {
        return $event->states()->contains(function (State $touched) use ($state) {
            if ($touched::class !== $state->state_type) {
                return false;
            }

            // Singletons match on type alone (their in-memory ids are
            // incidental), and ids compare in normalized string form to
            // mirror how the real store's queries match them.
            return is_a($state->state_type, SingletonState::class, true)
                || (string) Id::from($touched->id) === (string) $state->state_id;
        });
    }
}
