<?php

namespace Thunk\Verbs\Testing;

use Closure;
use Glhd\Bits\Bits;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ReflectsClosures;
use PHPUnit\Framework\Assert as PHPUnit;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateIdentity;
use Thunk\Verbs\Support\StateCollection;

class SnapshotStoreFake implements StoresSnapshots
{
    use ReflectsClosures;

    /** @var Collection<int, Collection<int, State>> */
    protected Collection $states;

    public function __construct()
    {
        $this->states = new Collection;
    }

    public function write(array $states): bool
    {
        foreach ($states as $state) {
            $this->states[$state::class] ??= new Collection;

            // Mirror the real store: singletons are keyed by type alone, so a
            // singleton only ever occupies one slot no matter its incidental id.
            $key = $state instanceof SingletonState ? Id::nil() : Id::from($state->id);

            $this->states[$state::class]->put($key, $state);
        }

        return true;
    }

    public function load(Bits|UuidInterface|AbstractUid|iterable|int|string $id, string $type): State|StateCollection|null
    {
        if (is_iterable($id)) {
            return StateCollection::make(collect($id)
                ->map(fn ($id) => $this->states[$type][Id::from($id)] ?? null)
                ->filter());
        }

        return $this->states[$type][Id::from($id)] ?? null;
    }

    public function loadSingleton(string $type): ?State
    {
        return Arr::first($this->states[$type]);
    }

    public function lastEventIdsFor(iterable $identities): Collection
    {
        return collect($identities)
            ->map(function (StateIdentity $identity) {
                $snapshots = $this->states->get($identity->state_type, new Collection);

                $snapshot = is_a($identity->state_type, SingletonState::class, true)
                    ? $snapshots->first()
                    : $snapshots->get(Id::from($identity->state_id));

                return $snapshot ? new StateIdentity(
                    state_type: $snapshot::class,
                    state_id: $snapshot instanceof SingletonState ? Id::nil() : Id::from($snapshot->id),
                    last_event_id: Id::tryFrom($snapshot->last_event_id),
                ) : null;
            })
            ->filter()
            ->values();
    }

    public function reset(): bool
    {
        $this->states = new Collection;

        return true;
    }

    public function assertWritten(string|Closure $state, Closure|int|null $callback = null): static
    {
        if ($state instanceof Closure) {
            [$state, $callback] = [$this->firstClosureParameterType($state), $state];
        }

        if (is_int($callback)) {
            return $this->assertWrittenTimes($state, $callback);
        }

        PHPUnit::assertTrue(
            $this->written($state, $callback)->count() > 0,
            "The expected [{$state}] state was not written."
        );

        return $this;
    }

    protected function assertWrittenTimes(string $class_name, int $times = 1): static
    {
        $count = $this->written($class_name)->count();

        PHPUnit::assertSame(
            expected: $times,
            actual: $count,
            message: "The expected [{$class_name}] state was written {$count} times instead of {$times} times.",
        );

        return $this;
    }

    /** @return Collection<int, State> */
    public function written(string $class_name, ?Closure $filter = null): Collection
    {
        if (! $this->hasWritten($class_name)) {
            return new Collection;
        }

        return $this->states[$class_name]
            ->when($filter !== null, fn ($events) => $events->filter($filter))
            ->values();
    }

    public function hasWritten($event): bool
    {
        return $this->states->has($event)
            && $this->states->get($event)->isNotEmpty();
    }

    public function assertNotWritten(string|Closure $state, ?Closure $callback = null): static
    {
        if ($state instanceof Closure) {
            [$state, $callback] = [$this->firstClosureParameterType($state), $state];
        }

        PHPUnit::assertCount(
            0, $this->written($state, $callback),
            "The unexpected [{$state}] state was written."
        );

        return $this;
    }

    public function assertNothingWritten(): static
    {
        PHPUnit::assertEmpty($this->states, 'States were written unexpectedly.');

        return $this;
    }
}
