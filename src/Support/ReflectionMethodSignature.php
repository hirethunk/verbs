<?php

namespace Thunk\Verbs\Support;

use ReflectionClass;
use ReflectionMethod;

class ReflectionMethodSignature
{
    public object $reflect;

    public ?string $prefix = null;

    public array $params = [];

    public static function make(object|string $instance_or_classname)
    {
        return new static($instance_or_classname);
    }

    public function __construct(object|string $instance_or_classname)
    {
        $this->reflect = new ReflectionClass($instance_or_classname);
    }

    public function prefix(string $prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function param(string $type, string $name = null)
    {
        $this->params[] = compact('type', 'name');

        return $this;
    }

    public function find()
    {
        $methods = collect($this->reflect->getMethods(ReflectionMethod::IS_PUBLIC));

        return $methods->when(
            $this->prefix,
            fn ($methods) => $methods->filter(
                fn ($method) => str_starts_with($method->getName(), $this->prefix)
            )
        )->when(
            ! empty($this->params),
            fn ($methods) => $methods->filter(
                fn ($method) => $this->includesParams($method, $this->params)
            )
        );
    }

    protected function includesParams(ReflectionMethod $method, array $params)
    {
        $method_params = collect($method->getParameters());

        return collect($params)->every(
            fn ($param) => $method_params->contains(
                fn ($method_param) => $method_param->getType()->getName() === $param['type']
                    && ($param['name'] ? $method_param->getName() === $param['name'] : true)
            )
        );
    }
}
