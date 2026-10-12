<?php

namespace Thunk\Verbs\Support\Normalization;

use ArrayObject;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Thunk\Verbs\Support\PropertyEncryption;
use Thunk\Verbs\Support\Serializer;

class EncryptedPropertiesNormalizer implements DenormalizerInterface, NormalizerInterface, SerializerAwareInterface
{
    use AcceptsNormalizerAndDenormalizer;

    const NORMALIZED_FLAG = 'verbs_encrypted_normalized';

    const DENORMALIZED_FLAG = 'verbs_encrypted_denormalized';

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return empty($context[self::DENORMALIZED_FLAG])
            && is_array($data)
            && $this->containsEnvelopes($data);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $context[self::DENORMALIZED_FLAG] = true;

        return $this->serializer->denormalize(
            data: PropertyEncryption::decrypt($data),
            type: $type,
            format: $format,
            context: $context,
        );
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return is_object($data)
            && ! isset($context[self::NORMALIZED_FLAG][spl_object_id($data)])
            && $data === app(Serializer::class)->active_normalization_target
            && PropertyEncryption::hasEncryptedProperties($data::class);
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): ArrayObject|array|string|int|float|bool|null
    {
        $context[self::NORMALIZED_FLAG][spl_object_id($object)] = true;

        $data = $this->serializer->normalize($object, $format, $context);

        return is_array($data)
            ? PropertyEncryption::encrypt($data, $object::class)
            : $data;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    protected function containsEnvelopes(array $data): bool
    {
        foreach ($data as $value) {
            if (PropertyEncryption::isEnvelope($value)) {
                return true;
            }
        }

        return false;
    }
}
