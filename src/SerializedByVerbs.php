<?php

namespace Thunk\Verbs;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

interface SerializedByVerbs
{
    public static function deserializeForVerbs(mixed $data, DenormalizerInterface $denormalizer): static;

    public function serializeForVerbs(NormalizerInterface $normalizer): string|array;
}
