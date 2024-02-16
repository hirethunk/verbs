<?php

namespace Thunk\Verbs;

use LogicException;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Support\EventStateRegistry;
use Thunk\Verbs\Support\PendingEvent;
use Thunk\Verbs\Support\StateCollection;
use WeakMap;

/**
 * @method static static fire(...$args)
 * @method static mixed commit(...$args)
 */
abstract class Event
{
    public int $id;

    public static function __callStatic(string $name, array $arguments)
    {
        return static::make()->$name(...$arguments);
    }

    /** @return PendingEvent<$this> */
    public static function make(...$args): PendingEvent
    {
        return PendingEvent::make(static::class, $args);
    }

    public function metadata(?string $key = null, mixed $default = null): mixed
    {
        return app(MetadataManager::class)->get($this, $key, $default);
    }

    public function states(): StateCollection
    {
        // TODO: This is a bit hacky, but is probably OK right now

        static $map = new WeakMap();

        return $map[$this] ??= app(EventStateRegistry::class)->getStates($this);
    }

    /**
     * @template TStateType of State
     *
     * @param  class-string<TStateType>|null  $state_type
     * @return TStateType|State|null
     */
    public function state(?string $state_type = null): ?State
    {
        $states = $this->states();

        if ($states->isEmpty()) {
            throw new LogicException(class_basename($this).' does not have any states.');
        }

        // If we only have one state, allow for accessing without providing a class
        if ($state_type === null && $states->count() === 1) {
            return $states->first();
        }

        return $states->firstWhere(fn (State $state) => $state::class === $state_type);
    }

    protected function assert($assertion, string $message): static
    {
        $result = (bool) value($assertion, $this);

        if (! $result) {
            throw new EventNotValidForCurrentState($message);
        }

        return $this;
    }
}
