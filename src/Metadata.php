<?php

namespace Thunk\Verbs;

use Illuminate\Support\Collection;

class Metadata
{
    protected Collection $extra;

    public function __construct(array $data = [])
    {
        $this->extra = new Collection($data);
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

    public function __sleep(): array
    {
        return $this->extra->toArray();
    }
}
