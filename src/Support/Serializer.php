<?php

namespace Thunk\Verbs\Support;

use BackedEnum;
use ReflectionClass;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\Event;
use Thunk\Verbs\Metadata;
use Thunk\Verbs\State;

class Serializer
{
    const TYPE_KEY = '__verbs_type';

    public $active_normalization_target = null;

    public function __construct(
        public SymfonySerializer $serializer,
        protected array $context = [],
    ) {}

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

    /**
     * Metadata is an untyped bag, so plain JSON can't round-trip object values
     * (a Carbon would come back as its ISO string). On write, each object
     * value is wrapped in a small type envelope with its class name; on read,
     * envelopes are revived through the configured normalizers, so a Carbon
     * comes back as a Carbon. Scalars and arrays are stored bare, and rows
     * written before envelopes existed read back as-is.
     */
    public function serializeMetadata(Metadata $metadata): string
    {
        return json_encode((object) $this->normalizeUntypedValue($metadata->all()));
    }

    public function deserializeMetadata(array $data): Metadata
    {
        return new Metadata(array_map($this->denormalizeUntypedValue(...), $data));
    }

    // The untyped-value codec deliberately uses the raw Symfony serializer:
    // routing through serialize()/deserialize() would leak the user-editable
    // serializer context into stored values and set the active normalization
    // target to the value itself, breaking State-in-metadata id-reduction.
    protected function normalizeUntypedValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->normalizeUntypedValue(...), $value);
        }

        if (is_object($value)) {
            return [
                static::TYPE_KEY => $value::class,
                'value' => $this->serializer->normalize($value, 'json'),
            ];
        }

        return $value;
    }

    protected function denormalizeUntypedValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $type = $value[static::TYPE_KEY] ?? null;

        if (is_string($type) && array_key_exists('value', $value) && count($value) === 2) {
            // An envelope whose class no longer exists still surfaces its
            // stored value—the data outlives the type.
            return class_exists($type)
                ? $this->serializer->denormalize($value['value'], $type, 'json')
                : $value['value'];
        }

        return array_map($this->denormalizeUntypedValue(...), $value);
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
