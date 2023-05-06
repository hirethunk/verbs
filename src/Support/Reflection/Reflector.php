<?php

namespace Thunk\Verbs\Support\Reflection;

use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;
use Thunk\Verbs\Attributes\ListenerAttribute;
use Thunk\Verbs\Events\Dispatcher\Listener;

class Reflector extends \Illuminate\Support\Reflector
{
	public static function getListeners(object $target): Collection
	{
		$reflect = new ReflectionClass($target);
		
		return collect($reflect->getMethods(ReflectionMethod::IS_PUBLIC))
			->filter(fn(ReflectionMethod $method) => $method->getNumberOfParameters())
			->map(fn(ReflectionMethod $method) => Listener::fromReflection($target, $method));
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
