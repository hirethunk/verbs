<?php

namespace Thunk\Verbs\Support\Normalization;

use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Thunk\Verbs\SerializedByVerbs;

class SelfSerializingNormalizer implements DenormalizerInterface, NormalizerInterface, SerializerAwareInterface
{
    protected NormalizerInterface|DenormalizerInterface $serializer;

    public function setSerializer(SerializerInterface $serializer)
    {
        if ($serializer instanceof NormalizerInterface && $serializer instanceof DenormalizerInterface) {
            $this->serializer = $serializer;

            return;
        }

        throw new InvalidArgumentException('The SelfSerializingNormalizer expects a serializer that implements both normalization and denormalization.');
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null): bool
    {
        return is_a($type, SerializedByVerbs::class, true);
    }

    /** @param  class-string<SerializedByVerbs>  $type */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): SerializedByVerbs
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return $type::deserializeForVerbs($data, $this->serializer);
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof SerializedByVerbs;
    }

    public function normalize(mixed $object, string $format = null, array $context = []): array|string
    {
        if (! $object instanceof SerializedByVerbs) {
            throw new InvalidArgumentException(class_basename($this).' can only normalize classes that implement SerializedByVerbs.');
        }

        return $object->serializeForVerbs($this->serializer);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [SerializedByVerbs::class => false];
    }
}
