<?php

namespace Thunk\Verbs\Support\Normalizers;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CollectionNormalizer implements DenormalizerInterface, NormalizerInterface
{
    public function supportsDenormalization(mixed $data, string $type, string $format = null): bool
    {
        return is_a($type, Collection::class, true);
    }

    /** @param  class-string<Collection>  $type */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Collection
    {
        return Collection::make($data);
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof Collection;
    }

    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        if (! $object instanceof Collection) {
            throw new InvalidArgumentException(class_basename($this).' can only normalize Carbon objects.');
        }

        return $object->all();
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Collection::class => false,
        ];
    }
}
