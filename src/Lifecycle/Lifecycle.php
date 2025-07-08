<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Container\Container;
use Thunk\Verbs\Event;

class Lifecycle
{
    public static function run(Event $event, Phases $phases): Event
    {
        $dispatcher = Container::getInstance()->make(Dispatcher::class);

        return (new static($dispatcher, $event, $phases))->handle();
    }

    public function __construct(
        public Dispatcher $dispatcher,
        public Event $event,
        public Phases $phases,
    ) {}

    public function handle(): Event
    {
        if ($this->phases->has(Phase::Boot)) {
            $this->dispatcher->boot($this->event);
        }

        $guards = null;
        if ($this->phases->has(Phase::Authorize)) {
            $guards ??= Guards::for($this->event);
            $guards->authorize();
        }

        if ($this->phases->has(Phase::Validate)) {
            $guards ??= Guards::for($this->event);
            $guards->validate();
        }

        if ($this->phases->has(Phase::Apply)) {
            $this->dispatcher->apply($this->event);
        }

        if ($this->phases->has(Phase::Handle)) {
            $this->dispatcher->handle($this->event);
        }

        if ($this->phases->has(Phase::Fired)) {
            $this->dispatcher->fired($this->event);
        }

        return $this->event;
    }
}
