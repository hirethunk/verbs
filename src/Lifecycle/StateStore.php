<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\State;
use Glhd\Bits\Snowflake;
use Illuminate\Support\Arr;
use UnexpectedValueException;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Lifecycle\Queue;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Models\VerbStateEvent;
use Illuminate\Database\Eloquent\Collection;

class StateStore
{
    protected array $stores = [];

    public function initialize(string $type, int|string $id = null): State
    {
        $state = new $type();
        $state->id = $id ?? Snowflake::make()->id();

        return $this->remember($state);
    }

    /** @param  class-string<State>  $type */
    public function load(int|string $id, string $type): State
    {
        if ($loaded = $this->stores[$type][(string) $id] ?? null) {
            return $loaded;
        }

        $snapshot = VerbSnapshot::find($id);

        if ($snapshot && $type !== $snapshot->type) {
            throw new UnexpectedValueException('State does not have a valid type.');
        }

        $stored_events = static::getEventsForState($id, $type, $snapshot?->last_event_id);

        $queued_events = app(Queue::class)->getEvents();

        if ($snapshot && $stored_events->isEmpty()) {
            return $type::initialize($id);
        }

        return $this->remember(
            $type::hydrate(
                $id,
                $snapshot?->data ?? [],
                $stored_events->toArray(),
            )
        );

    }

    public static function getEventsForState(
        int|string $id,
        string $type,
        int|string|null $cutoff_id = null,
    ): Collection
    {
         return VerbStateEvent::where([
            'state_id' => $id,
            'state_type' => $type,
        ])
            ->with('event')
            ->when($cutoff_id, fn ($query) => $query->where('id' , '>', $cutoff_id))
            ->get()
            ->map
            ->event;
    }

    public function writeLoaded(): bool
    {
        return $this->write(Arr::flatten($this->stores));
    }

    public function write(array $states): bool
    {
        return VerbSnapshot::insert(static::formatForWrite($states));
    }

    public function reset(): bool
    {
        VerbSnapshot::truncate();

        return true;
    }

    protected function remember(State $state): State
    {
        $this->stores[$state::class][(string) $state->id] = $state;

        return $state;
    }

    protected static function formatForWrite(array $states): array
    {
        return array_map(fn ($state) => [
            'type' => $state::class,
            'data' => json_encode(get_object_vars($state)),
            'created_at' => now(),
            'updated_at' => now(),
        ], $states);
    }
}
