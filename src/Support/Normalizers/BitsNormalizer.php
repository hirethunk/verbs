<?php

namespace Thunk\Verbs\Support\Normalizers;

use Glhd\Bits\Bits;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class BitsNormalizer implements DenormalizerInterface, NormalizerInterface
{
    public function supportsDenormalization(mixed $data, string $type, string $format = null): bool
    {
        return is_a($type, Bits::class, true);
    }

    /** @param  class-string<Bits>  $type */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Bits
    {
        return $type::coerce($data);
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof Bits;
    }

    public function normalize(mixed $object, string $format = null, array $context = []): string
    {
        if (! $object instanceof Bits) {
            throw new InvalidArgumentException(class_basename($this).' can only normalize Bits objects.');
        }

        return $object->jsonSerialize();
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Bits::class => false,
        ];
    }
}
