<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Collection;
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

    public function alias(?string $alias, string $key): static
    {
        if ($alias) {
            $this->aliases[$alias] = $key;
        }

        return $this;
    }

    public function get($key, $default = null)
    {
        if (! $this->has($key) && isset($this->aliases[$key])) {
            $key = $this->aliases[$key];
        }

        return parent::get($key, $default);
    }

    /** @param  class-string<State>  $state_type */
    public function ofType(string $state_type): static
    {
        return $this->filter(fn (State $state) => $state instanceof $state_type);
    }

    /** @param  class-string<State>  $state_type  */
    public function firstOfType(string $state_type): ?State
    {
        return $this->ofType($state_type)->first();
    }
}
