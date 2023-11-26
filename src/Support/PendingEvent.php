<?php

namespace Thunk\Verbs\Support;

use Closure;
use Glhd\Bits\Snowflake;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;
use Throwable;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Broker;

/**
 * @template T
 *
 * @property T $event
 */
class PendingEvent
{
    protected Closure $exception_mapper;

    /** @param  class-string<Event>  $class_name */
    public static function make(string $class_name, array $args): static
    {
        // Turn a positional array to an associative array
        if (count($args) && ! Arr::isAssoc($args)) {
            if (! method_exists($class_name, '__construct')) {
                throw new InvalidArgumentException('You cannot pass positional arguments to '.class_basename($class_name).'::make()');
            }

            // TODO: Cache this
            $args = collect((new ReflectionMethod($class_name, '__construct'))->getParameters())
                ->mapWithKeys(function (ReflectionParameter $parameter, $index) use ($args) {
                    return [
                        $parameter->getName() => match (true) {
                            isset($args[$index]) => $args[$index],
                            $parameter->isDefaultValueAvailable() => $parameter->getDefaultValue(),
                            $parameter->isOptional() => null,
                            $parameter->isVariadic() => throw new RuntimeException('Variadic positional arguments are not implemented.'),
                            default => throw new InvalidArgumentException("No valid value for '{$parameter->getName()}' provided."),
                        },
                    ];
                })
                ->all();
        }

        $event = app(EventSerializer::class)->deserialize($class_name, $args);
        $event->id = Snowflake::make()->id();

        return new static($event);
    }

    public function __construct(
        public Event $event
    ) {
        $this->event->id ??= Snowflake::make()->id();
        $this->exception_mapper = fn ($e) => $e;
    }

    public function shouldFire(): static
    {
        $this->autofire = true;

        return $this;
    }

    public function hydrate(array $data): static
    {
        app(EventSerializer::class)->deserialize($this->event, $data);

        return $this;
    }

    public function fire(...$args): Event
    {
        if (! empty($args)) {
            if (count($args) === 1 && isset($args[0]) && is_array($args[0])) {
                $args = $args[0];
            }

            $this->hydrate($args);
        }

        try {
            return app(Broker::class)->fire($this->event);
        } catch (Throwable $e) {
            throw $this->prepareException($e);
        }
    }

    public function fireNow(...$args): mixed
    {
        $event = $this->fire(...$args);

        $results = app(Broker::class)->commit()[$event];

        return count($results) > 1 ? $results : $results[0];
    }

    /** @param  callable(Throwable): Throwable  $handler */
    public function onError(Closure $handler): static
    {
        $this->exception_mapper = $handler;

        return $this;
    }

    protected function prepareException(Throwable $e): Throwable
    {
        $result = call_user_func($this->exception_mapper, $e);

        if (is_array($result)) {
            $result = ValidationException::withMessages($result);
        }

        return $result;
    }
}
