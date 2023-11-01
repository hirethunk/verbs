<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateSerializer;

class SnapshotStore
{
    public function load(int|string $id): ?State
    {
        $snapshot = VerbSnapshot::find($id);

        return $snapshot?->state();
    }

    public function write(array $states): bool
    {
        return VerbSnapshot::upsert(static::formatForWrite($states), 'id', ['data', 'last_event_id', 'updated_at']);
    }

    public function reset(): bool
    {
        VerbSnapshot::truncate();

        return true;
    }

    protected static function formatForWrite(array $states): array
    {
        return array_map(fn (State $state) => [
            'id' => $state->id,
            'type' => $state::class,
            'data' => app(StateSerializer::class)->serialize($state),
            'last_event_id' => $state->last_event_id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $states);
    }
}
