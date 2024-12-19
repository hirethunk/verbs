<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Exceptions\StateIsNotSingletonException;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Serializer;
use Thunk\Verbs\Support\StateCollection;

class SnapshotStore implements StoresSnapshots
{
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

        return $snapshots->first()?->state();
    }

    public function write(array $states): bool
    {
        if (! count($states)) {
            return true;
        }

        foreach (array_chunk($states, 20) as $chunk) {
            $upserted = VerbSnapshot::upsert(
                values: collect($chunk)->map($this->formatForWrite(...))->unique('id')->all(),
                uniqueBy: ['id'],
                update: ['data', 'last_event_id', 'updated_at'],
            );

            if (! $upserted) {
                return false;
            }
        }

        return true;
    }

    public function delete(Bits|UuidInterface|AbstractUid|int|string ...$ids): bool
    {
        $ids = array_map(Id::from(...), $ids);

        return VerbSnapshot::whereIn('state_id', $ids)->delete() === true;
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
            1 => $snapshots->first()->state(),
            default => throw new MultipleRecordsFoundException($count),
        };
    }

    protected function loadMany(Collection $ids, string $type): StateCollection
    {
        $ids->ensure([Bits::class, UuidInterface::class, AbstractUid::class, 'int', 'string']);

        $states = VerbSnapshot::query()
            ->where('type', '=', $type)
            ->whereIn('state_id', $ids)
            ->get()
            ->map(fn (VerbSnapshot $snapshot) => $snapshot->state());

        return StateCollection::make($states);
    }

    protected function formatForWrite(State $state): array
    {
        return [
            'id' => $this->metadata->getEphemeral($state, 'snapshot_id', snowflake_id()),
            'state_id' => Id::from($state->id),
            'type' => $state::class,
            'data' => $this->serializer->serialize($state),
            'last_event_id' => Id::tryFrom($state->last_event_id),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
