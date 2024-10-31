<?php

namespace Thunk\Verbs\Support\Normalization;

use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Serializer;

class StateNormalizer implements DenormalizerInterface, NormalizerInterface
{
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_a($type, State::class, true) && (is_numeric($data) || is_string($data) || is_a($data, State::class, true));
    }

    /** @param  class-string<State>  $type */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): State
    {
        if ($data instanceof State) {
            return $data;
        }

        return app(StateManager::class)->load($data, $type);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof State
            && $data !== app(Serializer::class)->active_normalization_target;
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): string
    {
        if (! $object instanceof State) {
            throw new InvalidArgumentException(class_basename($this).' can only normalize State objects.');
        }

        return (string) $object->id;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [State::class => false];
    }
}
