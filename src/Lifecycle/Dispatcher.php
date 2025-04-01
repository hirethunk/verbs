<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\MethodFinder;
use Thunk\Verbs\Support\Reflector;

class Dispatcher
{
    protected array $hooks = [];

    protected array $skipped_phases = [];

    public function __construct(
        protected Container $container
    ) {}

    public function register(string|object $target): void
    {
        foreach (Reflector::getHooks($target) as $hook) {
            foreach ($hook->targets as $fqcn) {
                $this->hooks[$fqcn][] = $hook;
            }
        }
    }

    public function skipPhases(Phase ...$phases): void
    {
        $this->skipped_phases = $phases;
    }

    public function boot(Event $event): void
    {
        if ($this->shouldDispatchPhase(Phase::Boot)) {
            $this->getBootHooks($event)->each(fn (Hook $hook) => $hook->boot($this->container, $event));
        }
    }

    public function validate(Event $event): bool
    {
        if (! $this->shouldDispatchPhase(Phase::Validate)) {
            return true;
        }

        foreach ($this->getValidationHooks($event) as $hook) {
            if (! $hook->validate($this->container, $event)) {
                throw new EventNotValidForCurrentState("Validation failed in '{$hook->name}'");
            }
        }

        return true;
    }

    public function apply(Event $event): void
    {
        if ($this->shouldDispatchPhase(Phase::Apply)) {
            $this->getApplyHooks($event)->each(fn (Hook $hook) => $hook->apply($this->container, $event));
        }

        $event->states()->each(fn (State $state) => $state->last_event_id = $event->id);
    }

    public function fired(Event $event): void
    {
        if ($this->shouldDispatchPhase(Phase::Fired)) {
            $this->getFiredHooks($event)->each(fn (Hook $hook) => $hook->fired($this->container, $event));
        }
    }

    public function handle(Event $event): Collection
    {
        if (! $this->shouldDispatchPhase(Phase::Handle)) {
            return collect();
        }

        return $this->getHandleHooks($event)->map(fn (Hook $hook) => $hook->handle($this->container, $event));
    }

    public function replay(Event $event): void
    {
        if ($this->shouldDispatchPhase(Phase::Replay)) {
            $this->getReplayHooks($event)->each(fn (Hook $hook) => $hook->replay($this->container, $event));
        }
    }

    /** @return Collection<int, Hook> */
    protected function getBootHooks(Event $event): Collection
    {
        $hooks = $this->hooksFor($event, Phase::Boot);

        if (method_exists($event, 'boot')) {
            $hooks->prepend(Hook::fromClassMethod($event, 'boot')->forcePhases(Phase::Boot));
        }

        return $hooks;
    }

    /** @return Collection<int, Hook> */
    protected function getFiredHooks(Event $event): Collection
    {
        $hooks = $this->hooksFor($event, Phase::Fired);

        if (method_exists($event, 'fired')) {
            $hooks->prepend(Hook::fromClassMethod($event, 'fired')->forcePhases(Phase::Fired));
        }

        return $hooks;
    }

    /** @return Collection<int, Hook> */
    protected function getHandleHooks(Event $event): Collection
    {
        $hooks = $this->hooksFor($event, Phase::Handle);

        if (method_exists($event, 'handle')) {
            $hooks->prepend(Hook::fromClassMethod($event, 'handle')->forcePhases(Phase::Handle, Phase::Replay));
        }

        return $hooks;
    }

    /** @return Collection<int, Hook> */
    protected function getReplayHooks(Event $event): Collection
    {
        $hooks = $this->hooksFor($event, Phase::Replay);

        if (method_exists($event, 'handle')) {
            $hooks->prepend(Hook::fromClassMethod($event, 'handle')->forcePhases(Phase::Handle, Phase::Replay));
        }

        return $hooks;
    }

    /** @return Collection<int, Hook> */
    protected function getAuthorizeHooks(Event $event): Collection
    {
        return $this->hooksFor($event, Phase::Authorize)
            ->merge($this->hooksWithPrefix($event, Phase::Authorize, 'authorize'));
    }

    /** @return Collection<int, Hook> */
    protected function getValidationHooks(Event $event): Collection
    {
        return $this->hooksFor($event, Phase::Validate)
            ->merge($this->hooksWithPrefix($event, Phase::Validate, 'validate'));
    }

    /** @return Collection<int, Hook> */
    protected function getApplyHooks(Event $event): Collection
    {
        // Get apply hooks on event
        $hooks = $this->hooksFor($event, Phase::Apply);
        $hooks = $hooks->merge($this->hooksWithPrefix($event, Phase::Apply, 'apply'));

        // Find apply hooks on any states that care about this event
        foreach ($event->states() as $state) {
            $hooks = $hooks->merge($this->hooksFor($state, Phase::Apply));
            $hooks = $hooks->merge($this->hooksWithPrefix($state, Phase::Apply, 'apply', expecting: $event::class));
        }

        return $hooks;
    }

    /** @return Collection<int, Hook> */
    protected function hooksWithPrefix(Event|State $target, Phase $phase, string $prefix, ?string $expecting = null): Collection
    {
        $finder = MethodFinder::for($target)->prefixed($prefix);

        if ($expecting) {
            $finder->expecting($expecting);
        }

        return $finder->map(fn (ReflectionMethod $name) => Hook::fromClassMethod($target, $name)->forcePhases($phase));
    }

    /** @return Collection<int, Hook> */
    protected function hooksFor(Event|State $target, ?Phase $phase = null): Collection
    {
        return Collection::make($this->hooks)
            ->only(Reflector::getClassInstanceOf($target))
            ->flatten()
            ->when($phase, fn (Collection $hooks) => $hooks->filter(fn (Hook $hook) => $hook->runsInPhase($phase)));
    }

    protected function shouldDispatchPhase(Phase $phase): bool
    {
        return ! in_array($phase, $this->skipped_phases);
    }
}
