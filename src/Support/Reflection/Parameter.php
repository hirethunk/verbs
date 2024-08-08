<?php

namespace Thunk\Verbs\Support\Reflection;

use Illuminate\Support\Traits\ForwardsCalls;
use ReflectionParameter;
use Thunk\Verbs\Support\NamedDependency;

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
        if ($value instanceof NamedDependency) {
            $value = $value->value;
        }

        return match (true) {
            $this->type()->isBuiltin() => get_debug_type($value) === $this->type()->name(),
            default => is_a($value, $this->type()->name(), true),
        };
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
