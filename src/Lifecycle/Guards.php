<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Throwable;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;
use Thunk\Verbs\Support\DependencyResolver;

class Guards
{
    public static function for(Event $event): static
    {
        return new static($event);
    }

    public function __construct(
        public Event $event
    ) {}

    /** @deprecated Call authorize()->validate() instead. */
    public function check(): static
    {
        trigger_error(
            'Guards::check() is deprecated — call authorize()->validate() instead.',
            E_USER_DEPRECATED,
        );

        return $this->authorize()->validate();
    }

    public function authorize(): static
    {
        if ($this->passesAuthorization()) {
            return $this;
        }

        if (method_exists($this->event, 'failedAuthorization')) {
            $this->event->failedAuthorization(null);
        }

        throw new AuthorizationException;
    }

    public function validate(): static
    {
        $exception = new EventNotValidForCurrentState;

        try {
            if ($this->passesValidation()) {
                return $this;
            }
        } catch (Throwable $thrown) {
            $exception = $thrown;
        }

        if (method_exists($this->event, 'failedValidation')) {
            $this->event->failedValidation($exception);
        }

        throw $exception;
    }

    protected function passesAuthorization(): bool
    {
        if (method_exists($this->event, 'authorize')) {
            $resolver = DependencyResolver::for($this->event->authorize(...), event: $this->event);
            $result = call_user_func_array([$this->event, 'authorize'], $resolver());

            if ($result instanceof Response) {
                $response = $result->authorize();

                return $response->allowed();
            }

            if (is_bool($result)) {
                return $result;
            }

            return true;
        }

        return true;
    }

    protected function passesValidation(): bool
    {
        return app(Dispatcher::class)->validate($this->event);
    }
}
