<?php

namespace Thunk\Verbs\Lifecycle;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use ReflectionMethod;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Reflector;

class Listener
{
	public static function fromClassMethod(object $target, ReflectionMethod $method): static
	{
		$listener = new static(
			callback: Closure::fromCallable([$target, $method->getName()]),
			events: Reflector::getEventParameters($method),
		);
		
		return Reflector::applyAttributes($method, $listener);
	}
	
	public static function fromClosure(Closure $callback): static
	{
		$listener = new static(
			callback: $callback,
			events: Reflector::getEventParameters($callback),
		);
		
		return Reflector::applyAttributes($callback, $listener);
	}
	
	public function __construct(
		public Closure $callback,
		public array $events = [],
		public bool $replayable = true,
	) {
	}
	
	public function handles(Event $event): bool
	{
		return in_array($event::class, $this->events);
	}
	
	public function handle(Event $event, Container $container): void
	{
		$container->call($this->callback, $this->guessEventParameter($event));
	}
	
	public function apply(Event $event, State $state, Container $container): void
	{
		$this->handle($event, $container);
		
		// FIXME:
		// $state->last_event_id = $event->id;
	}
	
	public function replay(Event $event, Container $container): void
	{
		if ($this->replayable) {
			$this->handle($event, $container);
		}
	}
	
	protected function guessEventParameter(Event $event): array
	{
		// This accounts for the following naming conventions (assuming `$event` is `UserSubscribed`)
		//
		//   - `$event`
		//   - `$userSubscribed`
		//   - `$user_subscribed`
		//
		// It also accounts for cases where the event has been typehinted.
		
		return [
			'event' => $event,
			$event::class => $event,
			(string) Str::of($event::class)->classBasename()->snake() => $event,
			(string) Str::of($event::class)->classBasename()->studly() => $event,
		];
	}
}
