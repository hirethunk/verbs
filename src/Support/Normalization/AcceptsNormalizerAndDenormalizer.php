<?php

namespace Thunk\Verbs\Support\Normalization;

use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

trait AcceptsNormalizerAndDenormalizer
{
    protected NormalizerInterface|DenormalizerInterface $serializer;

    public function setSerializer(SerializerInterface $serializer)
    {
        if ($serializer instanceof NormalizerInterface && $serializer instanceof DenormalizerInterface) {
            $this->serializer = $serializer;

            return;
        }

        throw new InvalidArgumentException(sprintf(
            'The %s expects a serializer that supports both normalization and denormalization.',
            class_basename($this)
        ));
    }
}
