<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Attributes\Hooks\DeferFor;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\EventStateRegistry;
use Thunk\Verbs\Support\StateCollection;
use Thunk\Verbs\Support\Wormhole;

class DeferredWriteQueue
{
    private array $callbacks = [];

    private int $count = 0;

    public function addHook(Event $event, DeferFor $deferred, callable $callback): void
    {
        /** @var string[] $propertyNames */
        $propertyNames = is_array($deferred->property_name) ? $deferred->property_name : [$deferred->property_name];

        $uniqueByKey = '';
        $states = new StateCollection;
        foreach ($propertyNames as $property) {
            if ($property === null) {
                $uniqueByKey .= 'null';

                continue;
            }

            $states = $states->merge(app(EventStateRegistry::class)->statesForProperty($event, $property));
        }

        $uniqueByKey .= $states->map(fn (State $state) => $state->id)->implode('|');

        $name = $deferred->name === DeferFor::EVENT_CLASS ? get_class($event) : $deferred->name;

        $this->callbacks[$name][$uniqueByKey][$this->count++] = [$event, $callback, true];
    }

    /**
     * @param  iterable<State|string|null>  $states
     */
    public function addCallback(iterable $states, callable $callback, string $name): void
    {
        $id = '';
        foreach ($states as $state) {
            if ($state === null) {
                $id .= 'null';

                continue;
            }
            if (is_string($state)) {
                $id .= $state;

                continue;
            }
            if ($state instanceof State) {
                $id .= $state->id;

                continue;
            }
            throw new \InvalidArgumentException('Invalid state type');
        }

        unset($this->callbacks[$name][$id]);
        $this->callbacks[$name][$id][$this->count++] = [null, $callback, false];
    }

    public function flush(): void
    {
        foreach ($this->callbacks as $namedCallbacks) {
            foreach ($namedCallbacks as $stateGroup) {
                $lastCallback = end($stateGroup);
                [$event, $callback, $isEventCallback] = $lastCallback;

                if ($isEventCallback) {
                    app(Wormhole::class)->warp($event, $callback);
                } else {
                    $callback();
                }
            }
        }

        $this->callbacks = [];
        $this->count = 0;
    }
}
