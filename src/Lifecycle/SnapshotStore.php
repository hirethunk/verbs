<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Exceptions\StateIsNotSingletonException;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Serializer;

class SnapshotStore implements StoresSnapshots
{
    public function __construct(
        protected MetadataManager $metadata,
        protected Serializer $serializer,
    ) {}

    public function load(Bits|UuidInterface|AbstractUid|int|string $id, string $type): ?State
    {
        $snapshot = VerbSnapshot::firstWhere([
            'state_id' => Id::from($id),
            'type' => $type,
        ]);

        return $snapshot?->state();
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
                update: ['data', 'last_event_id', 'updated_at']
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

    protected function formatForWrite(State $state): array
    {
        return [
            'id' => $this->metadata->getEphemeral($state, 'id', snowflake_id()),
            'state_id' => Id::from($state->id),
            'type' => $state::class,
            'data' => $this->serializer->serialize($state),
            'last_event_id' => Id::tryFrom($state->last_event_id),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
