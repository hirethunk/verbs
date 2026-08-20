<?php

namespace Thunk\Verbs\Support;

use BackedEnum;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;

class Serializer
{
    public mixed $active_normalization_target = null;

    public function __construct(
        public SymfonySerializer $serializer,
        protected array $context = [],
    ) {}

    public function serialize(object $class): string
    {
        // Build the context before __sleep, so we still have the original object
        $context = $this->serializationContext($class);
        $class_name = $class::class;

        if (method_exists($class, '__sleep')) {
            $class = $class->__sleep();
        }

        try {
            $this->active_normalization_target = $class;

            $json = $this->serializer->serialize($class, 'json', $context);
        } finally {
            $this->active_normalization_target = null;
        }

        if (PropertyEncryption::hasEncryptedProperties($class_name)
            && is_array($data = json_decode($json, true))) {
            $json = json_encode(PropertyEncryption::encrypt($data, $class_name));
        }

        return $json;
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
            $target = Reflector::newInstanceWithoutConstructorWithPromotedDefaults($target);
            $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $target;
        }

        // Encrypted properties are stored as ciphertext envelopes that have to
        // be unwrapped before Symfony sees them. Non-envelope values may be
        // plaintext from before the property was marked, and pass through.
        if (is_string($data) && str_contains($data, PropertyEncryption::MARKER)) {
            $data = json_decode($data, true);
        }

        if (is_array($data)) {
            $data = PropertyEncryption::decrypt($data);
        }

        // Symfony's denormalizer expects backed enums as their scalar value, so
        // when we're populating from an array we unwrap any enum instances first.
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
