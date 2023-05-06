<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;
use Thunk\Verbs\Attributes\ListenerAttribute;
use Thunk\Verbs\Events\Event;
use Thunk\Verbs\Events\Listener;

class Reflector extends \Illuminate\Support\Reflector
{
	/** @return Collection<int, Listener> */
	public static function getListeners(object $target): Collection
	{
		$reflect = new ReflectionClass($target);
		
		return collect($reflect->getMethods(ReflectionMethod::IS_PUBLIC))
			->filter(fn(ReflectionMethod $method) => $method->getNumberOfParameters())
			->map(fn(ReflectionMethod $method) => Listener::fromReflection($target, $method));
	}
	
	public function getEventParameters(ReflectionMethod $method): Collection
	{
		$class_names = Reflector::getParameterClassNames($method->getParameters()[0]);
		
		return collect($class_names)
			->filter(fn(string $class_name) => is_a($class_name, Event::class, true));
	}
	
	public static function applyAttributes(ReflectionMethod $method, Listener $listener): Listener
	{
		foreach ($method->getAttributes() as $attribute) {
			$instance = $attribute->newInstance();
			if ($instance instanceof ListenerAttribute) {
				$instance->applyToListener($listener);
			}
		}
		
		return $listener;
	}
}
