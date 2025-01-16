<?php

namespace Thunk\Verbs;

use BadMethodCallException;
use RuntimeException;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\Support\StateCollection;

abstract class SingletonState extends State
{
    public static function make(...$args): static
    {
        throw new BadMethodCallException('To use singleton states, please call ::singleton() rather than ::make()');
    }

    public static function new()
    {
        throw new BadMethodCallException('To use singleton states, please call ::singleton() rather than ::new()');
    }

    public static function loadOrFail($from): static
    {
        throw new BadMethodCallException('To use singleton states, please call ::singleton() rather than ::loadOrFail()');
    }

    public static function load($from): static|StateCollection
    {
        throw new BadMethodCallException('To use singleton states, please call ::singleton() rather than ::load()');
    }

    public static function loadByKey($from): static|StateCollection
    {
        throw new BadMethodCallException('To use singleton states, please call ::singleton() rather than ::loadByKey()');
    }

    public static function singleton(): static
    {
        return app(StateManager::class)->singleton(static::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::singleton();
    }

    public function resolveChildRouteBinding($childType, $value, $field)
    {
        throw new RuntimeException('Resolving child state via routing is not supported.');
    }
}
