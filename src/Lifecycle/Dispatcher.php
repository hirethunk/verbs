<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionMethod;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\ReflectionMethodSignature;
use Thunk\Verbs\Support\Reflector;
use Thunk\Verbs\Support\StateCollection;

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
                throw new EventNotValidForCurrentState("Validation failed in '{$hook->name}'");
            }
        }

        return true;
    }

    public function apply(Event $event, State $state): void
    {
        $this->getApplyHooks($event, $state)->each(fn (Hook $hook) => $hook->apply($this->container, $event, $state));
    }

    public function fired(Event $event, StateCollection $states): void
    {
        $this->getFiredHooks($event)->each(fn (Hook $hook) => $hook->fired($this->container, $event, $states));
    }

    public function handle(Event $event): void
    {
        $this->getHandleHooks($event)->each(fn (Hook $hook) => $hook->handle($this->container, $event));
    }

    public function replay(Event $event, State $state): void
    {
        $this->getReplayHooks($event)->each(fn (Hook $hook) => $hook->replay($this->container, $event, $state));
    }

    /** @return Collection<int, Hook> */
    protected function getFiredHooks(Event $event): Collection
    {
        $hooks = collect($this->hooks[$event::class] ?? []);

        if (method_exists($event, 'fired')) {
            $hooks->prepend(Hook::fromClassMethod($event, 'fired')->forcePhases(Phase::Fired));
        }

        return $hooks->filter(fn (Hook $hook) => $hook->runsInPhase(Phase::Fired));
    }

    /** @return Collection<int, Hook> */
    protected function getHandleHooks(Event $event): Collection
    {
        // FIXME: We need to handle interfaces, too

        $hooks = collect($this->hooks[$event::class] ?? []);

        if (method_exists($event, 'once')) {
            $hooks->prepend(Hook::fromClassMethod($event, 'once')->forcePhases(Phase::Handle));
        }

        if (method_exists($event, 'handle')) {
            $hooks->prepend(Hook::fromClassMethod($event, 'handle')->forcePhases(Phase::Handle, Phase::Replay));
        }

        return $hooks->filter(fn (Hook $hook) => $hook->runsInPhase(Phase::Handle));
    }

    /** @return Collection<int, Hook> */
    protected function getReplayHooks(Event $event): Collection
    {
        $hooks = collect($this->hooks[$event::class] ?? []);

        if (method_exists($event, 'handle')) {
            $hooks->prepend(Hook::fromClassMethod($event, 'handle')->forcePhases(Phase::Handle, Phase::Replay));
        }

        return $hooks->filter(fn (Hook $hook) => $hook->runsInPhase(Phase::Replay));
    }

    /** @return Collection<int, Hook> */
    protected function getValidationHooks(Event $event, State $state): Collection
    {
        $hooks = collect($this->hooks[$event::class] ?? []);

        $validation_hooks = collect(get_class_methods($event))
            // ->filter(fn (string $name) => $name !== 'validate' && Str::startsWith($name, 'validate'))
            ->filter(fn (string $name) => Str::startsWith($name, 'validate'))
            ->filter(fn (string $name) => ! empty(Reflector::getParametersOfType($state::class, new ReflectionMethod($event, $name))))
            ->map(fn (string $name) => Hook::fromClassMethod($event, $name)->forcePhases(Phase::Validate));

        // FIXME: We need to handle special `validate()` hook with no suffix

        return $hooks
            ->merge($validation_hooks)
            ->filter(fn (Hook $hook) => $hook->runsInPhase(Phase::Validate));
    }

    /** @return Collection<int, Hook> */
    protected function getApplyHooks(Event $event, State $state): Collection
    {
        $event_apply_methods = ReflectionMethodSignature::make($event)
            ->prefix('apply')
            ->param($state::class)
            ->find()
            ->map(fn (ReflectionMethod $method) => Hook::fromClassMethod($event, $method)->forcePhases(Phase::Apply));

        $states_apply_methods = ReflectionMethodSignature::make($state)
            ->prefix('apply')
            ->param($event::class)
            ->find()
            ->map(fn (ReflectionMethod $method) => Hook::fromClassMethod($state, $method)->forcePhases(Phase::Apply));

        return collect($this->hooks[$event::class] ?? [])
            ->merge($event_apply_methods)
            ->merge($states_apply_methods)
            ->filter(fn (Hook $hook) => $hook->runsInPhase(Phase::Apply));
    }
}
