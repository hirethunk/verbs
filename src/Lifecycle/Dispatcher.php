<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use ReflectionMethod;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;
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
        }
    }
	
	public function validate(Event $event, State $state): void
	{
		// FIXME
		foreach ($this->getHooks($event) as $listener) {
			$listener->validate($this->container, $event);
		}
	}
	
	public function apply(Event $event, State $state): void
	{
		foreach ($this->getAggregators($event, $state) as $listener) {
			$listener->apply($this->container, $event, $state);
		}
	}

    public function fire(Event $event): void
    {
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
	protected function getAggregators(Event $event, State $state): array
	{
		$listeners = $this->hooks[$event::class] ?? [];
		
		// FIXME: We need to filter listeners down to just those that apply this state
		
		collect(get_class_methods($event))
			->filter(fn(string $name) => Str::startsWith($name, 'apply'))
			->filter() // FIXME: We need to filter down to aggregators that handle this state
			->each(function(string $name) use (&$listeners, $event, $state) {
				$hook = Hook::fromClassMethod($event, new ReflectionMethod($event, $name));
				array_unshift($listeners, $hook);
			});
		
		return $listeners;
	}
}
