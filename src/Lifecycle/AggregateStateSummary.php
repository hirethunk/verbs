<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateIdentity;

class AggregateStateSummary
{
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

    protected function discover(): static
    {
        $this->discoverNewEventIds();

        do {
            $continue = $this->discoverNewStates() && $this->discoverNewEventIds();
        } while ($continue);

        $this->related_event_ids = $this->related_event_ids->sort();

        return $this;
    }

    protected function discoverNewEventIds(): bool
    {
        $new_event_ids = VerbStateEvent::query()
            ->distinct()
            ->select('event_id')
            ->whereNotIn('event_id', $this->related_event_ids)
            ->where(fn (Builder $query) => $this->related_states->each(
                fn ($state) => $query->orWhere(fn (Builder $query) => $this->addConstraint($state, $query)))
            )
            ->toBase()
            ->pluck('event_id');

        $this->related_event_ids = $this->related_event_ids->merge($new_event_ids);

        return $new_event_ids->isNotEmpty();
    }

    protected function discoverNewStates(): bool
    {
        $discovered_states = VerbStateEvent::query()
            ->orderBy('id')
            ->distinct()
            ->select(['state_id', 'state_type'])
            ->whereIn('event_id', $this->related_event_ids)
            ->where(fn (Builder $query) => $this->related_states->each(
                fn ($state) => $query->whereNot(fn (Builder $query) => $this->addConstraint($state, $query)))
            )
            ->toBase()
            ->chunkMap(StateIdentity::from(...));

        $this->related_states = $this->related_states->merge($discovered_states);

        return $discovered_states->isNotEmpty();
    }

    protected function addConstraint(StateIdentity $state, Builder $query): Builder
    {
        $query->where('state_type', '=', $state->state_type);
        $query->where('state_id', '=', $state->state_id);

        return $query;
    }
}
