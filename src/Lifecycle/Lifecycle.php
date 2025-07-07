<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Event;

class Lifecycle
{
    public function run(Event $event, Phases $phases): Event
    {
        return (new static(app(Dispatcher::class), $event, $phases))->handle();
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

        // FIXME: This is actually two phases
        if ($this->phases->has(Phase::Authorize)) {
            Guards::for($this->event)->check();
        }

        if ($this->phases->has(Phase::Apply)) {
            $this->dispatcher->apply($this->event);
        }

        // FIXME: Maybe we need a "commit" phase
        if ($this->phases->has(Phase::Handle)) {
            // FIXME
            // $this->queue->queue($this->event);
        }

        if ($this->phases->has(Phase::Fired)) {
            $this->dispatcher->fired($this->event);
        }

        return $this->event;
    }
}
