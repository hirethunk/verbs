<?php

namespace Thunk\Verbs\Support\Normalization;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CarbonNormalizer implements DenormalizerInterface, NormalizerInterface
{
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_a($type, CarbonInterface::class, true);
    }

    /** @param  class-string<CarbonInterface>  $type */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): CarbonInterface
    {
        return $type === CarbonInterface::class
            ? Date::parse($data)
            : $type::parse($data);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof CarbonInterface;
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): string
    {
        if (! $object instanceof CarbonInterface) {
            throw new InvalidArgumentException(class_basename($this).' can only normalize Carbon objects.');
        }

        return $object->jsonSerialize();
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            CarbonInterface::class => false,
        ];
    }
}
