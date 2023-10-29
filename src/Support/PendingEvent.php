<?php

namespace Thunk\Verbs\Support;

use Closure;
use Illuminate\Validation\ValidationException;
use Throwable;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Broker;

/**
 * @template T
 * @property T $event
 */
class PendingEvent
{
    protected Closure $exception_mapper;

    public static function make(Event $event): static
    {
        return new static($event);
    }

    public function __construct(
        public Event $event
    ) {
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
