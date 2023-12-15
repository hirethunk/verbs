<?php

namespace Thunk\Verbs\Support;

use BackedEnum;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

class Serializer
{
    public function __construct(
        public SymfonySerializer $serializer,
    ) {
    }

    public function serialize(object $class): string
    {
        if (method_exists($class, '__sleep')) {
            $class = $class->__sleep();
        }

        return $this->serializer->serialize($class, 'json');
    }

    public function deserialize(
        object|string $target,
        string|array $data
    ) {
        $type = $target;
        $context = [];

        if (is_object($target)) {
            $type = $target::class;
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
