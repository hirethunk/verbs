<?php

namespace Thunk\Verbs\Events;

use Closure;
use Illuminate\Contracts\Container\Container;
use ReflectionMethod;
use Thunk\Verbs\Support\Reflector;

class Listener
{
	public static function fromReflection(object $target, ReflectionMethod $method): Listener
	{
		$events = collect(Reflector::getParameterClassNames($method->getParameters()[0]))
			->filter(fn(string $class_name) => is_a($class_name, Event::class, true));
		
		$listener = new Listener(
			Closure::fromCallable([$target, $method->getName()]),
			$events->all(),
		);
		
		Reflector::applyAttributes($method, $listener);
		
		return $listener;
	}
	
	public function __construct(
		public Closure $listener,
		public array $events = [],
		public bool $replayable = true,
	) {
	}
	
	public function handle(Event $event, Container $container): void
	{
		$container->call($this->listener, [$event]);
	}
	
	public function replay(Event $event, Container $container): void
	{
		if ($this->replayable) {
			$this->handle($event, $container);
		}
	}
}
