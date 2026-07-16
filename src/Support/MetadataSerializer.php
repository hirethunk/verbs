<?php

namespace Thunk\Verbs\Support;

use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\Metadata;

/**
 * Metadata is an untyped bag, so plain JSON can't round-trip object values (a
 * Carbon would come back as its ISO string). On write, each object value is
 * wrapped in a small type envelope with its class name; on read, envelopes are
 * revived through the configured normalizers, so a Carbon comes back as a
 * Carbon. Scalars and arrays are stored bare, and rows written before
 * envelopes existed read back as-is.
 */
class MetadataSerializer
{
    const TYPE_KEY = '__verbs_type';

    public function __construct(
        protected SymfonySerializer $serializer,
    ) {}

    public function serialize(Metadata $metadata): string
    {
        return json_encode((object) $this->normalizeValue($metadata->all()));
    }

    public function deserialize(array $data): Metadata
    {
        return new Metadata(array_map($this->denormalizeValue(...), $data));
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->normalizeValue(...), $value);
        }

        if (is_object($value)) {
            return [
                static::TYPE_KEY => $value::class,
                'value' => $this->serializer->normalize($value, 'json'),
            ];
        }

        return $value;
    }

    protected function denormalizeValue(mixed $value): mixed
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

        return array_map($this->denormalizeValue(...), $value);
    }
}
