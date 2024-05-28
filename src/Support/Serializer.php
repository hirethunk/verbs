<?php

namespace Thunk\Verbs\Support;

use ReflectionClass;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;

class Serializer
{
    public $active_normalization_target = null;

    public function __construct(
        public SymfonySerializer $serializer,
        protected array $context = [],
    ) {
    }

    public function serialize(object $class): string
    {
        // Build the context before __sleep, so we still have the original object
        $context = $this->serializationContext($class);

        if (method_exists($class, '__sleep')) {
            $class = $class->__sleep();
        }

        try {
            $this->active_normalization_target = $class;

            return $this->serializer->serialize($class, 'json', $context);
        } finally {
            $this->active_normalization_target = null;
        }
    }

    public function deserialize(
        object|string $target,
        string|array $data,
        bool $call_constructor = false,
    ) {
        $type = $target;
        $context = $this->context;

        if (is_object($target)) {
            $type = $target::class;
            $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $target;
        }

        if (! $call_constructor && ! is_object($target)) {
            $reflect = new ReflectionClass($target);
            $target = $reflect->newInstanceWithoutConstructor();
            $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $target;
        }

        $callback = is_array($data)
            ? $this->serializer->denormalize(...)
            : $this->serializer->deserialize(...);

        return $callback(
            data: $data,
            type: $type,
            format: 'json',
            context: $context,
        );
    }

    protected function serializationContext(object $target): array
    {
        $context = [...$this->context];

        if ($target instanceof Event) {
            $context[AbstractNormalizer::IGNORED_ATTRIBUTES] = ['id'];
        }

        if ($target instanceof State) {
            $context[AbstractNormalizer::IGNORED_ATTRIBUTES] = ['id', 'last_event_id'];
        }

        return $context;
    }
}
