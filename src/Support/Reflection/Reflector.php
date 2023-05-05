<?php

namespace Thunk\Verbs\Support\Reflection;

use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;
use Thunk\Verbs\Attributes\Once;
use Thunk\Verbs\Events\Event;

class Reflector extends \Illuminate\Support\Reflector
{
	public static function hasOnceAttribute(ReflectionMethod $method): bool
	{
		return count($method->getAttributes(Once::class)) > 0;
	}
	
	public static function getListenerMethods(object $listener): Collection
	{
		$reflect = new ReflectionClass($listener);
		
		return collect($reflect->getMethods(ReflectionMethod::IS_PUBLIC))
			->filter(fn(ReflectionMethod $method) => $method->getNumberOfParameters())
			->flatMap(function(ReflectionMethod $method) use ($listener) {
				$once = static::hasOnceAttribute($method);
				return collect(static::getParameterClassNames($method->getParameters()[0]))
					->filter(fn(string $class_name) => is_a($class_name, Event::class, true))
					->map(fn($class_name) => new ListenerMethod($listener, $class_name, $method->getName(), $once))
					->all();
			});
	}
}
