<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Illuminate\Support\Arr;
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
    public function load(Bits|UuidInterface|AbstractUid|int|string $id, string $type): ?State
    {
        $snapshot = VerbSnapshot::whereKey(Id::from($id))->whereType($type)->first();

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

        $values = collect(static::formatForWrite($states))
            ->filter(fn ($state) => $state['__verbs_ephemeral'] === false)
            ->unique('id')
            ->map(fn ($state) => Arr::except($state, ['__verbs_ephemeral']))
            ->all();

        return VerbSnapshot::upsert($values, 'id', ['data', 'last_event_id', 'updated_at']);
    }

    public function reset(): bool
    {
        VerbSnapshot::truncate();

        return true;
    }

    protected static function formatForWrite(array $states): array
    {
        return array_map(fn (State $state) => [
            'id' => Id::from($state->id),
            'type' => $state::class,
            'data' => app(Serializer::class)->serialize($state),
            'last_event_id' => Id::tryFrom($state->last_event_id),
            'created_at' => now(),
            'updated_at' => now(),
            '__verbs_ephemeral' => $state->__verbs_ephemeral,
        ], $states);
    }
}
