<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\ConcurrencyException;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Serializer;

class EventStore implements StoresEvents
{
    public function __construct(
        protected MetadataManager $metadata,
    ) {}

    public function read(
        ?State $state = null,
        Bits|UuidInterface|AbstractUid|int|string|null $after_id = null,
        bool $singleton = false,
    ): LazyCollection {
        return $this->readEvents($state, $after_id, $singleton)
            ->each(fn (VerbEvent $model) => $this->metadata->set($model->event(), $model->metadata()))
            ->map(fn (VerbEvent $model) => $model->event());
    }

    public function get(iterable $ids): LazyCollection
    {
        return VerbEvent::query()
            ->whereIn('id', collect($ids))
            ->lazyById()
            ->each(fn (VerbEvent $model) => $this->metadata->set($model->event(), $model->metadata()))
            ->map(fn (VerbEvent $model) => $model->event());
    }

    public function write(array $events): bool
    {
        if (empty($events)) {
            return true;
        }

        $this->guardAgainstConcurrentWrites($events);

        return VerbEvent::insert($this->formatForWrite($events))
            && VerbStateEvent::insert($this->formatRelationshipsForWrite($events));
    }

    public function summarize(State $state, bool $singleton = false): AggregateStateSummary
    {
        $known_state_ids = $singleton ? new Collection : Collection::make([$state->id]);
        $known_event_ids = VerbStateEvent::query()
            ->distinct()
            ->select('event_id')
            ->where('state_type', $state::class)
            ->unless($singleton, fn (Builder $query) => $query->where('state_id', $state->id))
            ->toBase()
            ->pluck('event_id');

        do {
            $discovered_state_ids = VerbStateEvent::query()
                ->distinct()
                ->select('state_id')
                ->whereIn('event_id', $known_event_ids)
                ->whereNotIn('state_id', $known_state_ids)
                ->toBase()
                ->distinct()
                ->pluck('state_id');

            $known_state_ids = $known_state_ids->merge($discovered_state_ids);

            $discovered_event_ids = VerbStateEvent::query()
                ->distinct()
                ->select('event_id')
                ->whereNotIn('event_id', $known_event_ids)
                ->whereIn('state_id', $known_state_ids)
                ->toBase()
                ->pluck('event_id');

            $known_event_ids = $known_event_ids->merge($discovered_event_ids);

        } while ($discovered_event_ids->isNotEmpty());

        $aggregates = VerbSnapshot::query()
            ->toBase()
            ->tap(fn (BaseBuilder $query) => $query->select([
                $this->aggregateExpression($query, 'last_event_id', 'min'),
                $this->aggregateExpression($query, 'last_event_id', 'max'),
            ]))
            ->whereIn('state_id', $known_state_ids)
            ->first();

        return new AggregateStateSummary(
            state: $state,
            related_event_ids: $discovered_event_ids,
            related_state_ids: $discovered_state_ids,
            min_applied_event_id: $aggregates->min_last_event_id,
            max_applied_event_id: $aggregates->max_last_event_id,
        );
    }

    protected function readEvents(
        ?State $state,
        Bits|UuidInterface|AbstractUid|int|string|null $after_id,
        bool $singleton,
    ): LazyCollection {
        if ($state) {
            return VerbStateEvent::query()
                ->with('event')
                ->unless($singleton, fn (Builder $query) => $query->where('state_id', $state->id))
                ->where('state_type', $state::class)
                ->when($after_id, fn (Builder $query) => $query->whereRelation('event', 'id', '>', Id::from($after_id)))
                ->lazyById()
                ->map(fn (VerbStateEvent $pivot) => $pivot->event);
        }

        return VerbEvent::query()
            ->when($after_id, fn (Builder $query) => $query->where('id', '>', Id::from($after_id)))
            ->lazyById();
    }

    /** @param  Event[]  $events */
    protected function guardAgainstConcurrentWrites(array $events): void
    {
        $max_event_ids = new Collection;

        $query = VerbStateEvent::query()->toBase();

        $query->select([
            'state_type',
            'state_id',
            $this->aggregateExpression($query, 'event_id', 'max'),
        ]);

        $query->groupBy('state_type', 'state_id');
        $query->orderBy('state_id');

        $query->where(function (BaseBuilder $query) use ($events, $max_event_ids) {
            foreach ($events as $event) {
                foreach ($event->states() as $state) {
                    if (! $max_event_ids->has($key = $state::class.$state->id)) {
                        $query->orWhere(function (BaseBuilder $query) use ($state) {
                            $query->where('state_type', $state::class);
                            $query->where('state_id', $state->id);
                        });
                        $max_event_ids->put($key, $state->last_event_id);
                    }
                }
            }
        });

        // We can abort if there are no states associated with any of the
        // events that we're writing (since concurrency doesn't apply in that case)
        if ($max_event_ids->isEmpty()) {
            return;
        }

        $query->each(function ($result) use ($max_event_ids) {
            $state_type = data_get($result, 'state_type');
            $state_id = data_get($result, 'state_id');
            $max_written_id = (int) data_get($result, 'max_event_id');
            $max_expected_id = $max_event_ids->get($state_type.$state_id, 0);

            if ($max_written_id > $max_expected_id) {
                throw new ConcurrencyException("An event with ID {$max_written_id} has been written to the database for '{$state_type}' with ID {$state_id}. This is higher than the in-memory value of {$max_expected_id}.");
            }
        });
    }

    /** @param  Event[]  $event_objects */
    protected function formatForWrite(array $event_objects): array
    {
        return array_map(fn (Event $event) => [
            'id' => Id::from($event->id),
            'type' => $event::class,
            'data' => app(Serializer::class)->serialize($event),
            'metadata' => app(Serializer::class)->serialize($this->metadata->get($event)),
            'created_at' => app(MetadataManager::class)->getEphemeral($event, 'created_at', now()),
            'updated_at' => now(),
        ], $event_objects);
    }

    /** @param  Event[]  $event_objects */
    protected function formatRelationshipsForWrite(array $event_objects): array
    {
        return collect($event_objects)
            ->flatMap(fn (Event $event) => $event->states()->map(fn ($state) => [
                'id' => snowflake_id(),
                'event_id' => Id::from($event->id),
                'state_id' => Id::from($state->id),
                'state_type' => $state::class,
                'created_at' => now(),
                'updated_at' => now(),
            ]))
            ->all();
    }

    protected function aggregateExpression(BaseBuilder $query, string $column, string $function): Expression
    {
        return DB::raw(sprintf(
            '%s(%s) as %s',
            $function,
            $query->getGrammar()->wrap($column),
            $query->getGrammar()->wrapTable("{$function}_{$column}")
        ));
    }
}
