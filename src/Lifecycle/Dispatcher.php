<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use ReflectionMethod;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\ReflectionMethodSignature;
use Thunk\Verbs\Support\Reflector;

class Dispatcher
{
    protected array $hooks = [];

    public function __construct(
        protected Container $container
    ) {
    }

    public function register(object $target): void
    {
        foreach (Reflector::getHooks($target) as $hook) {
            foreach ($hook->events as $event_type) {
                $this->hooks[$event_type][] = $hook;
            }
            foreach ($hook->states as $state_type) {
                $this->hooks[$state_type][] = $hook;
            }
        }
    }

    public function validate(Event $event, State $state): bool
    {
        foreach ($this->getValidationHooks($event, $state) as $hook) {
            if (! $hook->validate($this->container, $event, $state)) {
                return false;
            }
        }

        return true;
    }

    public function apply(Event $event, State $state): void
    {
        foreach ($this->getApplyHooks($event, $state) as $hook) {
            $hook->apply($this->container, $event, $state);
        }
    }

    public function fire(Event $event): void
    {
        // FIXME: We need to be able to pass state into onFire
        foreach ($this->getHooks($event) as $listener) {
            $listener->fire($this->container, $event);
        }
    }

    public function replay(Event $event): void
    {
        foreach ($this->getHooks($event) as $listener) {
            $listener->replay($event, $this->container);
        }
    }

    /** @return \Thunk\Verbs\Lifecycle\Hook[] */
    protected function getValidationHooks(Event $event, State $state): array
    {
        $hooks = collect($this->hooks[$event::class] ?? []);

        collect(get_class_methods($event))
            ->filter(fn (string $name) => Str::startsWith($name, 'validate'))
            ->filter(function (string $name) use ($event, $state) {
                $method = new ReflectionMethod($event, $name);

                return ! empty(Reflector::getParametersOfType($state::class, $method));
            })
            ->each(function (string $name) use (&$hooks, $event) {
                $hook = Hook::fromClassMethod($event, new ReflectionMethod($event, $name));
                $hook->validates_state = true;
                $hooks->push($hook);
            });

        return $hooks
            ->filter(fn (Hook $hook) => $hook->validates_state)
            ->all();
    }

    /** @return \Thunk\Verbs\Lifecycle\Hook[] */
    protected function getHooks(Event $event): array
    {
        // FIXME: We need to handle interfaces, too

        $listeners = $this->hooks[$event::class] ?? [];

        // FIXME: We can lazily auto-discover here

        if (method_exists($event, 'onFire')) {
            $onFire = Hook::fromClassMethod($event, new ReflectionMethod($event, 'onFire'));
            array_unshift($listeners, $onFire);
        }

        if (method_exists($event, 'onCommit')) {
            $onCommit = Hook::fromClassMethod($event, new ReflectionMethod($event, 'onCommit'));
            $onCommit->replayable = false;
            array_unshift($listeners, $onCommit);
        }

        return $listeners;
    }

    /** @return \Thunk\Verbs\Lifecycle\Hook[] */
    protected function getApplyHooks(Event $event, State $state): array
    {
        $hooks = collect($this->hooks[$event::class] ?? []);

        $event_apply_methods = ReflectionMethodSignature::make($event)
            ->prefix('apply')
            ->param($state::class)
            ->find();

        $states_apply_methods = ReflectionMethodSignature::make($state)
            ->prefix('apply')
            ->param($event::class)
            ->find();

        $hooks = $hooks->merge(
            $event_apply_methods->map(fn (ReflectionMethod $method) => Hook::fromClassMethod($event, $method)->aggregatesState())
        )->merge(
            $states_apply_methods->map(fn (ReflectionMethod $method) => Hook::fromClassMethod($state, $method)->aggregatesState())
        );

        return $hooks
            ->filter(fn (Hook $hook) => $hook->aggregates_state)
            ->all();
    }
}
