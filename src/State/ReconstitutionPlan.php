<?php

namespace Thunk\Verbs\State;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;

/**
 * Computes everything a reconstitution needs: the connected component of
 * states (the members), the window of events to replay, the floor that window
 * starts from, and whether the members can be seeded from their snapshots.
 *
 * Seeding is only used when it is *exactly* equivalent to a blank rebuild:
 * every member's snapshot must sit at the window floor, so that no window
 * event was already absorbed by any seed. That's the common case, because
 * commit writes the snapshots of co-loaded states together. Anything murkier
 * falls back to a blank baseline over the full component—slower, never wrong.
 */
class ReconstitutionPlan
{
    /**
     * How many states may appear in a single discovery query's WHERE clause
     * (each contributes a few bound parameters), and how many event ids in a
     * single WHERE IN. Both stay well under every driver's parameter cap.
     */
    const STATE_CHUNK = 100;

    const EVENT_CHUNK = 500;

    /** @var Collection<int, State> */
    public Collection $original_states;

    /** @var Collection<int, StateIdentity> */
    public Collection $members;

    /** @var Collection<int, int|string> */
    public Collection $window;

    public int|string|null $floor = null;

    public bool $seeded = false;

    /** @var array<string, int|string|null> */
    protected array $positions = [];

    public static function plan(Collection $states, bool $use_snapshots = true): static
    {
        return (new static($states, $use_snapshots))->discover()->probe();
    }

    /** @param  Collection<int, State>  $states */
    public function __construct(
        Collection $states,
        protected bool $use_snapshots = true,
    ) {
        $this->original_states = $states->values()->collect();
        $this->members = new Collection;
        $this->window = new Collection;
    }

    public function events(): LazyCollection
    {
        return app(StoresEvents::class)->get($this->window);
    }

    /**
     * The seed instances for every member that has a snapshot, or null if any
     * of them can't be loaded anymore (deleted or unreadable since planning)—
     * in which case the caller must fall back to a blank rebuild.
     *
     * @return Collection<int, State>|null
     */
    public function seeds(): ?Collection
    {
        $positioned = $this->members->filter(
            fn (StateIdentity $member) => $this->positions[$this->stateKey($member)] !== null,
        );

        $snapshots = app(StoresSnapshots::class);
        $seeds = new Collection;

        foreach ($positioned->groupBy(fn (StateIdentity $member) => $member->state_type) as $type => $members) {
            if (is_a($type, SingletonState::class, true)) {
                $seeds->push($snapshots->loadSingleton($type));

                continue;
            }

            foreach ($members->chunk(static::EVENT_CHUNK) as $chunk) {
                $seeds = $seeds->merge(
                    $snapshots->load($chunk->map(fn (StateIdentity $member) => $member->state_id)->all(), $type),
                );
            }
        }

        return $seeds->contains(null) || $seeds->count() !== $positioned->count()
            ? null
            : $seeds->values();
    }

    /**
     * Breadth-first search over the bipartite state↔event graph, restricted to
     * events after the floor (the lowest member snapshot position). The
     * visited sets live in PHP and each round only queries the *newly
     * discovered* frontier in bounded chunks, so no query ever embeds the full
     * known set as bound parameters. When a new member drags the floor lower,
     * every member goes back into the pool so the expanded window is covered.
     */
    protected function discover(): static
    {
        $seen_states = [];
        $seen_events = [];

        $frontier = $this->original_states
            ->map(StateIdentity::from(...))
            ->filter(function (StateIdentity $state) use (&$seen_states) {
                return $this->markSeen($seen_states, $this->stateKey($state));
            })
            ->values();

        $this->members = $frontier->collect();
        $this->rememberPositions($frontier);
        $this->floor = $this->currentFloor();

        $pending = $frontier;

        while ($pending->isNotEmpty()) {
            $new_event_ids = $this
                ->eventIdsFor($pending)
                ->filter(function ($id) use (&$seen_events) {
                    return $this->markSeen($seen_events, $id);
                })
                ->values();

            if ($new_event_ids->isEmpty()) {
                break;
            }

            $this->window = $this->window->merge($new_event_ids);

            $found = $this
                ->statesFor($new_event_ids)
                ->filter(function (StateIdentity $state) use (&$seen_states) {
                    return $this->markSeen($seen_states, $this->stateKey($state));
                })
                ->values();

            $this->members = $this->members->merge($found);
            $this->rememberPositions($found);

            $floor = $this->currentFloor();

            if ($floor !== $this->floor) {
                $this->floor = $floor;
                $pending = $this->members;
            } else {
                $pending = $found;
            }
        }

        $this->window = $this->window->sort()->values();

        return $this;
    }

    /**
     * Seeding is exact only if no window event was already absorbed by a
     * member's snapshot. When every member sits exactly at the floor (the
     * common case) that's true by construction; otherwise one bounded query
     * checks for absorbed rows inside the window, and any hit means the whole
     * plan is recomputed from a blank baseline.
     */
    protected function probe(): static
    {
        if (! $this->use_snapshots) {
            return $this;
        }

        $misaligned = $this->members->filter(
            fn (StateIdentity $member) => $this->positions[$this->stateKey($member)] !== $this->floor,
        );

        if ($misaligned->isEmpty()) {
            $this->seeded = $this->floor !== null;

            return $this;
        }

        $absorbed_window_rows = $misaligned
            ->chunk(static::STATE_CHUNK)
            ->contains(function (Collection $chunk) {
                return VerbStateEvent::query()
                    ->when($this->floor !== null, fn (Builder $query) => $query->where('event_id', '>', $this->floor))
                    ->where(function (Builder $query) use ($chunk) {
                        foreach ($chunk as $member) {
                            $query->orWhere(function (Builder $query) use ($member) {
                                $this->addConstraint($member, $query);
                                $query->where('event_id', '<=', $this->positions[$this->stateKey($member)]);
                            });
                        }
                    })
                    ->exists();
            });

        if ($absorbed_window_rows) {
            $this->rediscoverBlank();
        } else {
            $this->seeded = true;
        }

        return $this;
    }

    /**
     * A blank-baseline plan is the same fixpoint with no positions at all:
     * floor drops away and discovery covers the entire connected component.
     */
    protected function rediscoverBlank(): void
    {
        $this->use_snapshots = false;
        $this->seeded = false;
        $this->members = new Collection;
        $this->window = new Collection;
        $this->positions = [];
        $this->floor = null;

        $this->discover();
    }

    /** @param  Collection<int, StateIdentity>  $identities */
    protected function rememberPositions(Collection $identities): void
    {
        foreach ($identities as $identity) {
            $this->positions[$this->stateKey($identity)] = null;
        }

        if (! $this->use_snapshots || $identities->isEmpty()) {
            return;
        }

        $identities
            ->chunk(static::STATE_CHUNK)
            ->each(function (Collection $chunk) {
                VerbSnapshot::query()
                    ->toBase()
                    ->select(['type', 'state_id', 'last_event_id'])
                    ->where(function ($query) use ($chunk) {
                        foreach ($chunk as $identity) {
                            $query->orWhere(function ($query) use ($identity) {
                                $query->where('type', $identity->state_type);

                                if (! is_a($identity->state_type, SingletonState::class, true)) {
                                    $query->where('state_id', $identity->state_id);
                                }
                            });
                        }
                    })
                    ->get()
                    ->each(function ($row) {
                        $key = is_a($row->type, SingletonState::class, true)
                            ? $row->type
                            : $row->type.':'.$row->state_id;

                        $this->positions[$key] = $this->normalizePosition($row->last_event_id);
                    });
            });
    }

    protected function currentFloor(): int|string|null
    {
        $positions = array_values($this->positions);

        return in_array(null, $positions, true) ? null : min($positions);
    }

    /** @param  Collection<int, StateIdentity>  $states */
    protected function eventIdsFor(Collection $states): Collection
    {
        return $states
            ->chunk(static::STATE_CHUNK)
            ->flatMap(function (Collection $chunk) {
                return VerbStateEvent::query()
                    ->distinct()
                    ->select('event_id')
                    ->when($this->floor !== null, fn (Builder $query) => $query->where('event_id', '>', $this->floor))
                    ->where(fn (Builder $query) => $chunk->each(
                        fn ($state) => $query->orWhere(fn (Builder $query) => $this->addConstraint($state, $query))),
                    )
                    ->toBase()
                    ->pluck('event_id');
            })
            ->unique()
            ->values();
    }

    /** @return Collection<int, StateIdentity> */
    protected function statesFor(Collection $event_ids): Collection
    {
        return $event_ids
            ->chunk(static::EVENT_CHUNK)
            ->flatMap(function (Collection $chunk) {
                return VerbStateEvent::query()
                    ->distinct()
                    ->select(['state_id', 'state_type'])
                    ->whereIn('event_id', $chunk->values()->all())
                    ->toBase()
                    ->get()
                    ->map(StateIdentity::from(...));
            })
            ->values();
    }

    protected function markSeen(array &$seen, int|string $key): bool
    {
        if (isset($seen[$key])) {
            return false;
        }

        $seen[$key] = true;

        return true;
    }

    /**
     * A singleton's identity is its type—its events are stored under whatever
     * incidental id the in-memory instance happened to have at write time—so
     * two rows for the same singleton type must collapse to one identity here.
     */
    protected function stateKey(StateIdentity $state): string
    {
        return is_a($state->state_type, SingletonState::class, true)
            ? $state->state_type
            : $state->state_type.':'.$state->state_id;
    }

    protected function addConstraint(StateIdentity $state, Builder $query): Builder
    {
        $query->where('state_type', '=', $state->state_type);

        // A singleton's identity is its type—its events are stored and read by
        // type alone (see EventStore::readEvents), so constraining by a specific
        // state_id here would miss them (e.g. when the seed is a blank singleton
        // with a freshly-minted id and no snapshot to anchor the real one).
        if (! is_a($state->state_type, SingletonState::class, true)) {
            $query->where('state_id', '=', $state->state_id);
        }

        return $query;
    }

    protected function normalizePosition(mixed $value): int|string|null
    {
        return match (true) {
            $value === null => null,
            is_numeric($value) => (int) $value,
            default => (string) $value,
        };
    }
}
