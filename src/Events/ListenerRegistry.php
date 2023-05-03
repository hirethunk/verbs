<?php

namespace Thunk\Verbs\Events;

class ListenerRegistry
{
    protected $listeners = [];

    public function register(string $class_name)
    {
        $this->listeners[] = $class_name;
    }

    public function getListeners()
    {
        return $this->listeners;
    }

    public function passEventToListeners($event, bool $is_replay = false)
    {
        foreach ($this->listeners as $listener) {
            $listener::handle($event, $is_replay);
        }
    }
}
