<?php

namespace Thunk\Verbs\Support\Reflection;

use Illuminate\Support\Traits\ForwardsCalls;
use ReflectionParameter;

/** @mixin ReflectionParameter */
class Parameter
{
    use ForwardsCalls;

    protected ?Type $type = null;

    public function __construct(
        public ReflectionParameter $target,
    ) {}

    public function accepts(mixed $value): bool
    {
        return $this->type()->includes($value);
    }

    public function type(): Type
    {
        return $this->type ??= new Type($this);
    }

    public function __call(string $name, array $arguments)
    {
        return $this->forwardDecoratedCallTo($this->target, $name, $arguments);
    }

    public function __get(string $name)
    {
        return $this->target->{$name};
    }
}
