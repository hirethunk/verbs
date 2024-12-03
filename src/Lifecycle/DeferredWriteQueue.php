<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Attributes\Hooks\UniqueBy;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\EventStateRegistry;
use Thunk\Verbs\Support\StateCollection;
use Thunk\Verbs\Support\Wormhole;

class DeferredWriteQueue
{
    private array $callbacks = [];

    public function addHook(Event $event, UniqueBy $deferred, callable $callback): void
    {
        /** @var string[] $propertyNames */
        $propertyNames = is_array($deferred->property) ? $deferred->property : [$deferred->property];

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

        $name = $deferred->name ?? 'DeferredWriteQueue';

        $this->callbacks[$name][$uniqueByKey] = [$event, $callback, true];
    }

    /**
     * @param  iterable<State|null>  $states
     */
    public function addCallback(iterable $states, callable $callback, string $name): void
    {
        $id = '';
        foreach ($states as $state) {
            if ($state === null) {
                $id .= 'null';

                continue;
            }
            $id .= $state->id;
        }

        $this->callbacks[$name][$id] = [null, $callback, false];
    }

    public function flush(): void
    {
        foreach ($this->callbacks as $callbacks) {
            foreach ($callbacks as $callback) {
                if ($callback[2]) {
                    app(Wormhole::class)->warp($callback[0], $callback[1]);
                } else {
                    $callback[1]();
                }
            }
        }
        $this->callbacks = [];
    }
}
