<?php

namespace Thunk\Verbs;

use ArrayAccess;
use Illuminate\Support\Collection;

class Metadata implements ArrayAccess
{
    protected Collection $extra;

    public function __construct(array $data = [])
    {
        $this->extra = new Collection;

        $this->merge($data);
    }

    public function merge(iterable $data): static
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }

        return $this;
    }

    public function __get(string $name)
    {
        return $this->extra->get($name);
    }

    public function __set(string $name, $value): void
    {
        $this->extra->put($name, $value);
    }

    public function __isset(string $name): bool
    {
        return $this->extra->has($name);
    }

    public function __unset(string $name): void
    {
        $this->extra->forget($name);
    }

    public function __sleep(): array
    {
        return $this->extra->toArray();
    }

    public function offsetExists($offset): bool
    {
        return isset($this->$offset);
    }

    public function offsetGet($offset): mixed
    {
        return $this->$offset;
    }

    public function offsetSet($offset, $value): void
    {
        $this->$offset = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->$offset);
    }
}
