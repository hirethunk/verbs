<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Glhd\Bits\Snowflake;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\ConcurrencyException;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Metadata;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\EventSerializer;
use Thunk\Verbs\Support\MetadataSerializer;

class EventStore
{
    /** @var callable[] */
    protected static array $createMetadataCallbacks = [];

    public static function createMetadataUsing(?callable $callback = null): void
    {
        if (is_null($callback)) {
            static::$createMetadataCallbacks = [];
        } else {
            static::$createMetadataCallbacks[] = $callback;
        }
    }

    public function read(
        State $state = null,
        Bits|UuidInterface|AbstractUid|int|string $after_id = null,
        Bits|UuidInterface|AbstractUid|int|string $up_to_id = null
    ): LazyCollection {
        if ($state) {
            return VerbStateEvent::query()
                ->with('event')
                ->where('state_id', $state->id)
                ->where('state_type', $state::class)
                ->when($after_id, fn (Builder $query) => $query->whereRelation('event', 'id', '>', Verbs::toId($after_id)))
                ->when($up_to_id, fn (Builder $query) => $query->whereRelation('event', 'id', '<=', Verbs::toId($up_to_id)))
                ->lazyById()
                ->map(fn (VerbStateEvent $pivot) => $pivot->event->event());
        }

        return VerbEvent::query()->lazyById();
    }

    /** @param  Event[]  $events */
    public function write(array $events): bool
    {
        if (empty($events)) {
            return true;
        }

        $this->guardAgainstConcurrentWrites($events);

        return VerbEvent::insert(static::formatForWrite($events))
            && VerbStateEvent::insert(static::formatRelationshipsForWrite($events));
    }

    /** @param  Event[]  $events */
    protected function guardAgainstConcurrentWrites(array $events): void
    {
        $max_event_ids = new Collection();

        $query = VerbStateEvent::query()->toBase();

        $query->select([
            'state_type',
            'state_id',
            DB::raw(sprintf(
                'max(%s) as %s',
                $query->getGrammar()->wrap('event_id'),
                $query->getGrammar()->wrapTable('max_event_id')
            )),
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

        $query->each(function ($result) use ($max_event_ids) {
            $state_type = data_get($result, 'state_type');
            $state_id = (int) data_get($result, 'state_id');
            $max_written_id = (int) data_get($result, 'max_event_id');
            $max_expected_id = $max_event_ids->get($state_type.$state_id, 0);

            if ($max_written_id > $max_expected_id) {
                throw new ConcurrencyException("An event with ID {$max_written_id} has been written to the database for '{$state_type}' with ID {$state_id}. This is higher than the in-memory value of {$max_expected_id}.");
            }
        });
    }

    protected static function withCreateMetadataHooks(): Metadata
    {
        $metadata = new Metadata();
        if (! empty(static::$createMetadataCallbacks)) {
            foreach (static::$createMetadataCallbacks as $callback) {
                $metadata = $callback($metadata);
            }
        }

        return $metadata;
    }

    /** @param  Event[]  $event_objects */
    protected static function formatForWrite(array $event_objects): array
    {
        return array_map(fn (Event $event) => [
            'id' => Verbs::toId($event->id),
            'type' => $event::class,
            'data' => app(EventSerializer::class)->serialize($event),
            'metadata' => app(MetadataSerializer::class)->serialize(static::withCreateMetadataHooks()),
            'created_at' => now(),
            'updated_at' => now(),
        ], $event_objects);
    }

    /** @param  Event[]  $event_objects */
    protected static function formatRelationshipsForWrite(array $event_objects): array
    {
        return collect($event_objects)
            ->flatMap(fn (Event $event) => $event->states()->map(fn ($state) => [
                'id' => Snowflake::make()->id(),
                'event_id' => Verbs::toId($event->id),
                'state_id' => Verbs::toId($state->id),
                'state_type' => $state::class,
            ]))
            ->all();
    }
}
