<?php

namespace Thunk\Verbs\Support;

use Closure;
use Illuminate\Validation\ValidationException;
use Throwable;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Broker;

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
        foreach ($data as $key => $value) {
            $this->event->{$key} = $value;
        }

        return $this;
    }

    public function fire(...$args): Event
    {
        if (! empty($args)) {
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
