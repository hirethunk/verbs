<?php

namespace Thunk\Verbs\Support;

use BackedEnum;
use ReflectionClass;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

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
        if (method_exists($class, '__sleep')) {
            $class = $class->__sleep();
        }

        try {
            $this->active_normalization_target = $class;

            return $this->serializer->serialize($class, 'json', $this->context);
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
        $context = [...$this->context];

        if (is_object($target)) {
            $type = $target::class;
            $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $target;
        }

        if (! $call_constructor && ! is_object($target)) {
            $reflect = new ReflectionClass($target);
            $target = $reflect->newInstanceWithoutConstructor();
            $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $target;
        }

        // FIXME: Symfony's serializer is a little wonky. May need to re-think things.
        if (is_array($data)) {
            $data = array_map(fn ($value) => $value instanceof BackedEnum
                ? $value->value
                : $value, $data);
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
}
