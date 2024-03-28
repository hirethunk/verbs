<?php

namespace Thunk\Verbs;

use Glhd\Bits\Bits;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Exceptions\StateNotFoundException;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\Support\Serializer;

abstract class State
{
    public Bits|UuidInterface|AbstractUid|int|string|null $id = null;

    public Bits|UuidInterface|AbstractUid|int|string|null $last_event_id = null;

    // TODO: This should move to state metadata eventually
    public bool $__verbs_initialized = false;

    public static function make(...$args): static
    {
        if ((count($args) === 1 && isset($args[0]) && is_array($args[0]))) {
            $args = $args[0];
        }

        $state = app(Serializer::class)->deserialize(static::class, $args);

        app(StateManager::class)->register($state);

        return $state;
    }

    /** @return StateFactory<static> */
    public static function factory(
        array|callable|int|null $count = null,
        array|callable|null $data = null
    ): StateFactory {
        if (is_array($count) || is_callable($count)) {
            throw_if($data !== null, new InvalidArgumentException('You cannot pass data to both factory arguments.'));
            [$data, $count] = [$count, null];
        }

        return static::newFactory()
            ->when($count !== null, fn (StateFactory $factory) => $factory->count($count))
            ->when($data !== null, fn (StateFactory $factory) => $factory->state($data));
    }

    protected static function newFactory(): StateFactory
    {
        return StateFactory::new(static::class);
    }

    public static function loadOrFail($from): static
    {
        $result = static::load($from);

        if ($result->last_event_id === null) {
            throw StateNotFoundException::forState(static::class, static::normalizeKey($from));
        }

        return $result;
    }

    public static function load($from): static
    {
        return static::loadByKey(static::normalizeKey($from));
    }

    public static function loadByKey($from): static
    {
        return app(StateManager::class)->load($from, static::class);
    }

    protected static function normalizeKey(mixed $from)
    {
        return is_object($from) && method_exists($from, 'getVerbsStateKey')
            ? $from->getVerbsStateKey()
            : $from;
    }

    public static function singleton(): static
    {
        return app(StateManager::class)->singleton(static::class);
    }

    public function storedEvents()
    {
        return app(StoresEvents::class)
            ->read(state: $this)
            ->collect();
    }

    public function fresh(): static
    {
        return app(StateManager::class)->load($this->id, static::class);
    }
}
