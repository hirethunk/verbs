<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Snowflake;
use Illuminate\Support\Arr;
use Thunk\Verbs\Examples\Subscriptions\States\SubscriptionState;
use Thunk\Verbs\State;
use Thunk\Verbs\VerbSnapshot;
use UnexpectedValueException;

class StateStore
{
    protected array $stores = [];

    public function initialize(string $type, int|string $id = null): State
    {
        $state = new $type();
        $state->id = $id ?? Snowflake::make()->id();

        return $this->remember($state);
    }

    public function load(int|string $id, string $type): State
    {
        if ($loaded = $this->stores[$type][(string) $id] ?? null) {
            return $loaded;
        }

        if ($snapshot = VerbSnapshot::find($id)) {
            if ($type !== $snapshot->type) {
                throw new UnexpectedValueException('State does not have a valid type.');
            }

            return $this->remember($snapshot->type::hydrate($snapshot->data));
        }

        return $type::initialize($id);
    }

    public function writeLoaded(): bool
    {
        return $this->write(Arr::flatten($this->stores));
    }

    public function write(array $states): bool
    {
        return VerbSnapshot::insert(self::formatForWrite($states));
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
