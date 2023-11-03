<?php

namespace Thunk\Verbs\Support\Normalizers;

use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Thunk\Verbs\SerializedByVerbs;

class SelfSerializingNormalizer implements DenormalizerInterface, NormalizerInterface
{
    public function supportsDenormalization(mixed $data, string $type, string $format = null): bool
    {
        return is_a($type, SerializedByVerbs::class, true);
    }

    /** @param  class-string<SerializedByVerbs>  $type */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): SerializedByVerbs
    {
        return $type::deserializeForVerbs($data);
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof SerializedByVerbs;
    }

    public function normalize(mixed $object, string $format = null, array $context = []): string
    {
        if (! $object instanceof SerializedByVerbs) {
            throw new InvalidArgumentException(class_basename($this).' can only normalize classes that implement SerializedByVerbs.');
        }

        return $object->serializeForVerbs();
    }

    public function getSupportedTypes(?string $format): array
    {
        return [SerializedByVerbs::class => false];
    }
}
