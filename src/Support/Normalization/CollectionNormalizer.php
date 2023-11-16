<?php

namespace Thunk\Verbs\Support\Normalization;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class CollectionNormalizer implements DenormalizerInterface, NormalizerInterface, SerializerAwareInterface
{
    protected NormalizerInterface|DenormalizerInterface $serializer;

    public function setSerializer(SerializerInterface $serializer)
    {
        if ($serializer instanceof NormalizerInterface && $serializer instanceof DenormalizerInterface) {
            $this->serializer = $serializer;

            return;
        }

        throw new InvalidArgumentException('The CollectionNormalizer expects a serializer that implements both normalization and denormalization.');
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null): bool
    {
        return is_a($type, Collection::class, true);
    }

    /** @param  class-string<Collection>  $type */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Collection
    {
        $fqcn = data_get($data, 'fqcn', Collection::class);
        $items = data_get($data, 'items', []);

        if ($items === []) {
            return new $fqcn;
        }

        $subtype = data_get($data, 'type');
        if ($subtype === null) {
            throw new InvalidArgumentException('Cannot denormalize a Collection that has no type information.');
        }

        return $fqcn::make($items)->map(fn ($value) => $this->serializer->denormalize($value, $subtype));
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof Collection;
    }

    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        if (! $object instanceof Collection) {
            throw new InvalidArgumentException(class_basename($this).' can only normalize Collection objects.');
        }

        $types = $object->map(fn ($value) => get_debug_type($value))->unique();

        if ($types->count() > 1) {
            throw new InvalidArgumentException(sprintf(
                'Cannot serialize a %s containing mixed types (got %s).',
                class_basename($object),
                $types->map(fn ($fqcn) => class_basename($fqcn))->implode(', ')
            ));
        }

        return array_filter([
            'fqcn' => $object::class === Collection::class ? null : $object::class,
            'type' => $types->first(),
            'items' => $object->map(fn ($value) => $this->serializer->normalize($value, $format, $context))->all(),
        ]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Collection::class => false];
    }
}
