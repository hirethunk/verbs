<?php

namespace Thunk\Verbs\Events\Dispatcher;

use Closure;
use ReflectionMethod;
use Thunk\Verbs\Events\Event;
use Thunk\Verbs\Support\Reflection\Reflector;

class Listener
{
    public static function fromReflection(object $target, ReflectionMethod $method): Listener
    {

        $events = collect(Reflector::getParameterClassNames($method->getParameters()[0]))
            ->filter(fn (string $class_name) => is_a($class_name, Event::class, true));

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
        public bool $once = false,
    ) {
    }

    public function __invoke(...$args)
    {
        return call_user_func_array($this->listener, $args);
    }
}
