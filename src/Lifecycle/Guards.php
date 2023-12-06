<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Throwable;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;
use Thunk\Verbs\State;

class Guards
{
    public static function for(Event $event, ?State $state = null): static
    {
        return new static($event, $state);
    }

    public function __construct(
        public Event $event,
        public ?State $state = null,
    ) {
    }

    public function check(): static
    {
        return $this->authorize()->validate();
    }

    public function authorize(): static
    {
        $this->event->phase = Phase::Authorize;

        if ($this->passesAuthorization()) {
            return $this;
        }

        if (method_exists($this->event, 'failedAuthorization')) {
            $this->event->failedAuthorization($this->state);
        }

        throw new AuthorizationException();
    }

    public function validate(): static
    {
        $this->event->phase = Phase::Validate;

        $exception = new EventNotValidForCurrentState();

        try {
            if ($this->passesValidation()) {
                return $this;
            }
        } catch (Throwable $thrown) {
            $exception = $thrown;
        }

        if (method_exists($this->event, 'failedValidation')) {
            $this->event->failedValidation($this->state);
        }

        throw $exception;
    }

    protected function passesAuthorization(): bool
    {
        if (method_exists($this->event, 'authorize')) {
            $result = app()->call([$this->event, 'authorize']);

            return $result instanceof Response
                ? $result->authorize()
                : $result;
        }

        return true;
    }

    protected function passesValidation(): bool
    {
        return app(Dispatcher::class)
            ->validate($this->event, $this->state);
    }
}
