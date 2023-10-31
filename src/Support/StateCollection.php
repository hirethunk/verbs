<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Collection;

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
}
