<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Event;
use Thunk\Verbs\Support\DeferredWriteData;

class DeferredWriteQueue
{
    private array $callbacks = [];
    public function add(Event $event, callable $callback, DeferredWriteData $deferred): void
    {
        $class = $deferred->class_name ?? get_class($event);
        $uniqueBy = $deferred->unique_by;
        $uniqueByKey = (string)$event->$uniqueBy ?? 'Default';

        $this->callbacks[$class][$uniqueByKey] = $callback;
    }

    public function flush(): void
    {
        foreach ($this->callbacks as $callbacks) {
            foreach ($callbacks as $callback) {
                $callback();
            }
        }
    }
}
