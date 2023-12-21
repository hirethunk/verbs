<?php

namespace Thunk\Verbs\Support;

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\State;

/**
 * @template TKey of array-key
 *
 * @implements \ArrayAccess<TKey, State>
 * @implements \Illuminate\Support\Enumerable<TKey, State>
 */
class StateCollection extends Collection
{
    protected array $aliases = [];

    public function alias(?string $alias, State $state): static
    {
        if ($alias) {
            $this->aliases[$alias] = [$state::class, $state->id];
        }

        return $this;
    }

    public function get($key, $default = null)
    {
        if (! $this->has($key) && isset($this->aliases[$key])) {
            [$target_class, $target_id] = $this->aliases[$key];

            return $this->first(
                fn (State $state) => $state instanceof $target_class && $state->id === $target_id,
                $default
            );
        }

        return parent::get($key, $default);
    }

    /** @param  class-string<State>  $state_type */
    public function ofType(string $state_type): static
    {
        return $this->filter(fn (State $state) => $state instanceof $state_type);
    }

    public function withId(Bits|UuidInterface|AbstractUid|int|string|null $id): static
    {
        return $this->filter(fn (State $state) => $state->id === $id);
    }

    /** @param  class-string<State>  $state_type  */
    public function firstOfType(string $state_type): ?State
    {
        return $this->ofType($state_type)->first();
    }

    public function filter(?callable $callback = null)
    {
        $result = parent::filter($callback);

        $result->aliases = $this->aliases;

        return $result;
    }
}
