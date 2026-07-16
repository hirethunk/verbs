<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateIdentity;

class AggregateStateSummary
{
    /**
     * How many states may appear in a single discovery query's WHERE clause
     * (each contributes up to two bound parameters), and how many event ids in
     * a single WHERE IN. Both stay well under every driver's parameter cap.
     */
    const STATE_CHUNK = 100;

    const EVENT_CHUNK = 500;

    public static function summarize(State ...$states): static
    {
        $summary = new static(
            original_states: Collection::make($states),
            related_event_ids: new Collection,
            related_states: Collection::make($states)->map(StateIdentity::from(...)),
        );

        return $summary->discover();
    }

    /**
     * @param  Collection<int, State>  $original_states
     * @param  Collection<int, int>  $related_event_ids
     * @param  Collection<int, StateIdentity>  $related_states
     */
    public function __construct(
        public Collection $original_states = new Collection,
        public Collection $related_event_ids = new Collection,
        public Collection $related_states = new Collection,
    ) {}

    public function events(): Enumerable
    {
        return app(StoresEvents::class)->get($this->related_event_ids);
    }

    /**
     * Breadth-first search over the bipartite state↔event graph. The visited
     * sets live in PHP and each round only queries the *newly discovered*
     * frontier in bounded chunks, so no query ever embeds the full known set
     * as bound parameters—a component of any size stays under driver limits.
     */
    protected function discover(): static
    {
        $seen_states = [];
        $seen_events = [];

        $frontier = $this->related_states
            ->filter(function (StateIdentity $state) use (&$seen_states) {
                return $this->markSeen($seen_states, $this->stateKey($state));
            })
            ->values();

        $this->related_states = $frontier->collect();

        while ($frontier->isNotEmpty()) {
            $new_event_ids = $this
                ->eventIdsFor($frontier)
                ->filter(function ($id) use (&$seen_events) {
                    return $this->markSeen($seen_events, $id);
                })
                ->values();

            if ($new_event_ids->isEmpty()) {
                break;
            }

            $this->related_event_ids = $this->related_event_ids->merge($new_event_ids);

            $frontier = $this
                ->statesFor($new_event_ids)
                ->filter(function (StateIdentity $state) use (&$seen_states) {
                    return $this->markSeen($seen_states, $this->stateKey($state));
                })
                ->values();

            $this->related_states = $this->related_states->merge($frontier);
        }

        $this->related_event_ids = $this->related_event_ids->sort()->values();

        return $this;
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
}
