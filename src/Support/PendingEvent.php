<?php

namespace Thunk\Verbs\Support;

use Closure;
use Glhd\Bits\Snowflake;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;
use Throwable;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Broker;
use Thunk\Verbs\Lifecycle\MetadataManager;

/**
 * @template T
 *
 * @property T $event
 */
class PendingEvent
{
    use Conditionable;

    protected Closure $exception_mapper;

    /** @param  class-string<Event>  $class_name */
    public static function make(string $class_name, array $args): static
    {
        $args = static::normalizeArgs($args);

        if (count($args) && ! Arr::isAssoc($args)) {
            $args = static::inferNamesForPositionalArgs($class_name, $args);
        }

        return (new static($class_name))
            ->when(count($args), fn (self $pending) => $pending->hydrate($args));
    }

    protected static function normalizeArgs(array $args): array
    {
        if (count($args) === 1 && isset($args[0]) && is_array($args[0])) {
            $args = $args[0];
        }

        return $args;
    }

    protected static function inferNamesForPositionalArgs(string $class_name, array $args): array
    {
        if (! method_exists($class_name, '__construct')) {
            throw new InvalidArgumentException('You cannot pass positional arguments to '.class_basename($class_name));
        }

        // TODO: Cache this
        return collect((new ReflectionMethod($class_name, '__construct'))->getParameters())
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

    public function __construct(
        public Event|string|null $event,
    ) {
        $this->conditionallySetId();
        $this->setDefaultExceptionMapper();
    }

    public function shouldFire(): static
    {
        $this->autofire = true;

        return $this;
    }

    public function hydrate(array $data): static
    {
        $this->event = app(EventSerializer::class)->deserialize($this->event, $data);

        $this->conditionallySetId();

        return $this;
    }

    public function fire(...$args): Event
    {
        if (! empty($args) || is_string($this->event)) {
            $this->hydrate(static::normalizeArgs($args));
        }

        try {
            return app(Broker::class)->fire($this->event);
        } catch (Throwable $e) {
            throw $this->prepareException($e);
        }
    }

	// FIXME: This name *may* change, so be prepared to refactor
    public function fireAndCommit(...$args): mixed
    {
        $event = $this->fire(...$args);

        app(Broker::class)->commit();

        $results = app(MetadataManager::class)->getLastResults($event);

        return $results->count() > 1 ? $results : $results->first();
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

    protected function setDefaultExceptionMapper(): void
    {
        $this->exception_mapper = fn ($e) => $e;
    }

    protected function conditionallySetId(): void
    {
        if ($this->event instanceof Event) {
            $this->event->id ??= Snowflake::make()->id();
        }
    }
}
