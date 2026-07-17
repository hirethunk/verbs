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
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateIdentity;
use Thunk\Verbs\Support\Serializer;

class EventStore implements StoresEvents
{
    /**
     * How many states may appear in a single query's WHERE clause (each
     * contributes a few bound parameters), and how many event ids in a single
     * WHERE IN. Both stay well under every database driver's parameter cap.
     */
    protected const STATE_CHUNK = 100;

    protected const EVENT_CHUNK = 500;

    public function __construct(
        protected MetadataManager $metadata,
    ) {}

    public function read(
        ?State $state = null,
        Bits|UuidInterface|AbstractUid|int|string|null $after_id = null,
    ): LazyCollection {
        return $this->toEventsWithMetadata($this->readEvents($state, $after_id));
    }

    public function get(iterable $ids): LazyCollection
    {
        // Chunking bounds the number of bound parameters per query (SQLite in
        // particular caps them), no matter how many ids the caller passes. The
        // ids are sorted first so the concatenated chunks stream in id order.
        $ids = collect($ids)->map(Id::from(...))->unique()->sort()->values();

        return $this->toEventsWithMetadata(
            LazyCollection::make(function () use ($ids) {
                foreach ($ids->chunk(static::EVENT_CHUNK) as $chunk) {
                    $models = VerbEvent::query()
                        ->whereIn('id', $chunk)
                        ->orderBy('id')
                        ->get();

                    foreach ($models as $model) {
                        yield $model;
                    }
                }
            })
        );
    }

    public function hasUnappliedEvents(iterable $identities): bool
    {
        return collect($identities)
            ->chunk(static::STATE_CHUNK)
            ->contains(fn (Collection $chunk) => $this->anyUnappliedEvents($chunk));
    }

    public function hasAppliedEventsAfter(iterable $identities, int|string|null $after_id = null): bool
    {
        return collect($identities)
            // An identity with no last_event_id has applied nothing, so no row
            // can ever fall inside its (after_id, last_event_id] window.
            ->filter(fn (StateIdentity $identity) => $identity->last_event_id !== null)
            ->chunk(static::STATE_CHUNK)
            ->contains(function (Collection $chunk) use ($after_id) {
                return VerbStateEvent::query()
                    ->toBase()
                    ->when($after_id !== null, fn (BaseBuilder $query) => $query->where('event_id', '>', $after_id))
                    ->where(function (BaseBuilder $query) use ($chunk) {
                        foreach ($chunk as $identity) {
                            $query->orWhere(function (BaseBuilder $query) use ($identity) {
                                $this->constrainToIdentity($query, $identity);
                                $query->where('event_id', '<=', $identity->last_event_id);
                            });
                        }
                    })
                    ->exists();
            });
    }

    public function eventIdsFor(iterable $identities, int|string|null $after_id = null): Collection
    {
        return collect($identities)
            ->chunk(static::STATE_CHUNK)
            ->flatMap(function (Collection $chunk) use ($after_id) {
                return VerbStateEvent::query()
                    ->toBase()
                    ->distinct()
                    ->select('event_id')
                    ->when($after_id !== null, fn (BaseBuilder $query) => $query->where('event_id', '>', $after_id))
                    ->where(function (BaseBuilder $query) use ($chunk) {
                        foreach ($chunk as $identity) {
                            $query->orWhere(fn (BaseBuilder $query) => $this->constrainToIdentity($query, $identity));
                        }
                    })
                    ->pluck('event_id');
            })
            ->unique()
            ->values();
    }

    public function stateIdentitiesFor(iterable $event_ids): Collection
    {
        return collect($event_ids)
            ->chunk(static::EVENT_CHUNK)
            ->flatMap(function (Collection $chunk) {
                return VerbStateEvent::query()
                    ->toBase()
                    ->distinct()
                    ->select(['state_id', 'state_type'])
                    ->whereIn('event_id', $chunk->values()->all())
                    ->get()
                    ->map(StateIdentity::from(...));
            })
            ->unique(fn (StateIdentity $identity) => $identity->key())
            ->values();
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

    /** @param  Collection<int, StateIdentity>  $identities */
    protected function anyUnappliedEvents(Collection $identities): bool
    {
        $query = VerbStateEvent::query()->toBase();

        $query->select([
            'state_type',
            'state_id',
            $this->aggregateExpression($query, 'event_id', 'max'),
        ]);

        $query->groupBy('state_type', 'state_id');

        $query->where(function (BaseBuilder $query) use ($identities) {
            foreach ($identities as $identity) {
                $query->orWhere(fn (BaseBuilder $query) => $this->constrainToIdentity($query, $identity));
            }
        });

        $latest = $query->get();

        foreach ($identities as $identity) {
            $rows = $latest->where('state_type', $identity->state_type);

            if (! is_a($identity->state_type, SingletonState::class, true)) {
                $rows = $rows->where('state_id', $identity->state_id);
            }

            // A singleton's events may be recorded under several incidental
            // state_id rows, so its true last event id is the max across all of them.
            $max = $rows->max('max_event_id');

            if (! $max) {
                continue;
            }

            // No int casts: snowflake ids compare numerically either way,
            // and ULID/UUIDv7 ids compare lexicographically-by-time.
            $applied = $identity->last_event_id ?? 0;

            if ($max > $applied) {
                return true;
            }
        }

        return false;
    }

    protected function constrainToIdentity(BaseBuilder $query, StateIdentity $identity): BaseBuilder
    {
        $query->where('state_type', '=', $identity->state_type);

        // A singleton's identity is its type—its events are stored and read by
        // type alone (see readEvents), so constraining by a specific state_id
        // here would miss rows written under other incidental ids.
        if (! is_a($identity->state_type, SingletonState::class, true)) {
            $query->where('state_id', '=', $identity->state_id);
        }

        return $query;
    }

    protected function toEventsWithMetadata(LazyCollection $models): LazyCollection
    {
        return $models->tapEach(fn (VerbEvent $model) => $this->metadata->set($model->event(), $model->metadata()))
            ->map(fn (VerbEvent $model) => $model->event());
    }

    protected function readEvents(
        ?State $state,
        Bits|UuidInterface|AbstractUid|int|string|null $after_id,
    ): LazyCollection {
        if ($state) {
            return VerbStateEvent::query()
                ->with('event')
                ->unless($state instanceof SingletonState, fn (Builder $query) => $query->where('state_id', $state->id))
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
                    if (! $max_event_ids->has($key = $this->guardKey($state::class, $state->id))) {
                        $query->orWhere(function (BaseBuilder $query) use ($state) {
                            $query->where('state_type', $state::class);

                            // A singleton's events may be recorded under several
                            // incidental state_id rows (see constrainToIdentity),
                            // and a conflicting writer's rows carry *its* incidental
                            // id—so the guard must match by type alone.
                            if (! $state instanceof SingletonState) {
                                $query->where('state_id', $state->id);
                            }
                        });
                        $max_event_ids->put($key, Id::normalizeEventId($state->last_event_id));
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

            // No int casts: snowflake ids compare numerically either way,
            // and ULID/UUIDv7 ids compare lexicographically-by-time.
            $max_written_id = Id::normalizeEventId(data_get($result, 'max_event_id'));
            $max_expected_id = $max_event_ids->get($this->guardKey($state_type, $state_id), 0);

            if ($max_written_id > $max_expected_id) {
                throw new ConcurrencyException("An event with ID {$max_written_id} has been written to the database for '{$state_type}' with ID {$state_id}. This is higher than the in-memory value of {$max_expected_id}.");
            }
        });
    }

    protected function guardKey(string $state_type, mixed $state_id): string
    {
        return (new StateIdentity($state_type, Id::from($state_id)))->key();
    }

    /** @param  Event[]  $event_objects */
    protected function formatForWrite(array $event_objects): array
    {
        return array_map(fn (Event $event) => [
            'id' => Id::from($event->id),
            'type' => $event::class,
            'data' => app(Serializer::class)->serialize($event),
            'metadata' => app(Serializer::class)->serializeMetadata($this->metadata->get($event)),
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
            ->values()
            ->all();
    }

    protected function aggregateExpression(BaseBuilder $query, string $column, string $function): Expression
    {
        return DB::raw(sprintf(
            '%s(%s) as %s',
            $function,
            $query->getGrammar()->wrap($column),
            $query->getGrammar()->wrapTable("{$function}_{$column}"),
        ));
    }
}
