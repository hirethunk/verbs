<?php

namespace Thunk\Verbs;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LogicException;
use Throwable;
use Thunk\Verbs\Exceptions\EventNotAuthorized;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Support\EventStateRegistry;
use Thunk\Verbs\Support\PendingEvent;
use Thunk\Verbs\Support\StateCollection;

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

    /** @return ($key is empty ? Metadata : mixed) */
    public function metadata(?string $key = null, mixed $default = null): mixed
    {
        return app(MetadataManager::class)->get($this, $key, $default);
    }

    public function states(): StateCollection
    {
        return app(EventStateRegistry::class)->getStates($this);
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

    protected function assert($assertion, ?string $exception = null, ?string $message = null): static
    {
        if ($exception && $message === null && ! is_a($exception, Throwable::class, true)) {
            [$message, $exception] = [$exception, null];
        }

        if ($message === null) {
            $message = 'The event is not valid';
        }

        if ($exception === null) {
            $caller = Arr::first(
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2),
                static fn ($trace) => ($trace['function'] ?? 'assert') !== 'assert'
            )['function'] ?? 'validate';

            $exception = match (true) {
                Str::startsWith($caller, 'authorize') => EventNotAuthorized::class,
                Str::startsWith($caller, 'validate') => EventNotValidForCurrentState::class,
                default => EventNotValid::class,
            };
        }

        $result = (bool) value($assertion, $this);

        if ($result === true) {
            return $this;
        }

        throw new $exception($message);
    }
}
