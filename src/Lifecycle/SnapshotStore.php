<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Throwable;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Exceptions\StateIsNotSingletonException;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateIdentity;
use Thunk\Verbs\Support\Serializer;
use Thunk\Verbs\Support\StateCollection;

class SnapshotStore implements StoresSnapshots
{
    /**
     * How many states may appear in a single query's WHERE clause (each
     * contributes a couple of bound parameters), and how many state ids in a
     * single WHERE IN. Both stay well under every database driver's parameter cap.
     */
    const STATE_CHUNK = 100;

    const ID_CHUNK = 500;

    public function __construct(
        protected MetadataManager $metadata,
        protected Serializer $serializer,
    ) {}

    public function load(Bits|UuidInterface|AbstractUid|iterable|int|string $id, string $type): State|StateCollection|null
    {
        return is_iterable($id)
            ? $this->loadMany(collect($id), $type)
            : $this->loadOne($id, $type);
    }

    public function loadSingleton(string $type): ?State
    {
        $snapshots = VerbSnapshot::query()
            ->where('type', $type)
            ->limit(2)
            ->get();

        if ($snapshots->count() > 1) {
            throw new StateIsNotSingletonException($type);
        }

        return $snapshots->isEmpty() ? null : $this->stateFromSnapshot($snapshots->first());
    }

    public function lastEventIdsFor(iterable $identities): Collection
    {
        return collect($identities)
            ->chunk(static::STATE_CHUNK)
            ->flatMap(function (Collection $chunk) {
                return VerbSnapshot::query()
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
                    ->get();
            })
            ->map(fn ($row) => new StateIdentity(
                state_type: $row->type,
                state_id: $row->state_id,
                last_event_id: Id::normalizeEventId($row->last_event_id),
            ))
            ->values();
    }

    public function write(array $states): bool
    {
        if (! count($states)) {
            return true;
        }

        foreach (array_chunk($states, 20) as $chunk) {
            $upserted = VerbSnapshot::upsert(
                values: collect($chunk)
                    ->map($this->formatForWrite(...))
                    ->unique(fn (array $row) => $row['type'].':'.$row['state_id'])
                    ->all(),
                uniqueBy: ['type', 'state_id'],
                update: ['data', 'last_event_id', 'updated_at'],
            );

            if (! $upserted) {
                return false;
            }
        }

        return true;
    }

    public function reset(): bool
    {
        VerbSnapshot::truncate();

        return true;
    }

    protected function loadOne(Bits|UuidInterface|AbstractUid|int|string $id, string $type): ?State
    {
        // This mimics "sole" but returns null if no records are found rather than triggering an exception

        $snapshots = VerbSnapshot::query()
            ->where('type', '=', $type)
            ->where('state_id', $id)
            ->take(2)
            ->get();

        $count = $snapshots->count();

        return match ($count) {
            0 => null,
            1 => $this->stateFromSnapshot($snapshots->first()),
            default => throw new MultipleRecordsFoundException($count),
        };
    }

    protected function loadMany(Collection $ids, string $type): StateCollection
    {
        $ids->ensure([Bits::class, UuidInterface::class, AbstractUid::class, 'int', 'string']);

        $states = $ids
            ->chunk(static::ID_CHUNK)
            ->flatMap(fn (Collection $chunk) => VerbSnapshot::query()
                ->where('type', '=', $type)
                ->whereIn('state_id', $chunk->values())
                ->get())
            ->map(fn (VerbSnapshot $snapshot) => $this->stateFromSnapshot($snapshot))
            ->filter();

        return StateCollection::make($states->values());
    }

    /**
     * A snapshot that can't be trusted—undeserializable data, or data with no
     * last_event_id—is treated as absent so the state transparently rebuilds
     * from its events, rather than wedging every request that touches it.
     */
    protected function stateFromSnapshot(VerbSnapshot $snapshot): ?State
    {
        if ($snapshot->last_event_id === null) {
            Log::warning('Verbs: snapshot has data but no last_event_id; rebuilding from events.', [
                'type' => $snapshot->type,
                'state_id' => $snapshot->state_id,
            ]);

            return null;
        }

        try {
            return $snapshot->state();
        } catch (Throwable $exception) {
            Log::warning('Verbs: failed to deserialize snapshot; rebuilding from events.', [
                'type' => $snapshot->type,
                'state_id' => $snapshot->state_id,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function formatForWrite(State $state): array
    {
        return [
            'id' => snowflake_id(),
            // A singleton's identity is its type, so its row gets the sentinel
            // id—giving every singleton exactly one row under the natural key.
            'state_id' => $state instanceof SingletonState ? Id::nil() : Id::from($state->id),
            'type' => $state::class,
            'data' => $this->serializer->serialize($state),
            'last_event_id' => Id::tryFrom($state->last_event_id),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
