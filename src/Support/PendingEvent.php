<?php

namespace Thunk\Verbs\Support;

use Closure;
use Throwable;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Broker;

class PendingEvent
{
    protected bool $should_fire = false;

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
        $this->should_fire = true;

        return $this;
    }

    public function hydrate(array $data): static
    {
        foreach ($data as $key => $value) {
            $this->event->{$key} = $value;
        }

        return $this;
    }

    public function fire(...$args)
    {
        if (func_num_args()) {
            $this->hydrate($args);
        }

        return $this->shouldFire();
    }

    public function forceFire(): Event
    {
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

    public function __destruct()
    {
        if ($this->should_fire) {
            $this->should_fire = false;
            $this->forceFire();
        }
    }

    protected function prepareException(Throwable $e): Throwable
    {
        return call_user_func($this->exception_mapper, $e);
    }
}
