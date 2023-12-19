<?php

namespace Thunk\Verbs\Support\Normalization;

use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Contracts\Queue\QueueableCollection;
use Illuminate\Contracts\Queue\QueueableEntity;
use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Thunk\Verbs\Exceptions\DoNotStoreModelsOnEventsOrStates;

class ModelNormalizer implements DenormalizerInterface, NormalizerInterface
{
    use SerializesAndRestoresModelIdentifiers;

    protected static $allow_normalization = false;

    public static function dangerouslyAllowModelNormalization(bool $allow_normalization = true): void
    {
        static::$allow_normalization = $allow_normalization;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null): bool
    {
        return $this->typeSupportsModelIdentifiers($type) && $this->isDenormalizedModelIdentifier($data);
    }

    /** @param  class-string<QueueableEntity|QueueableCollection>  $type */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): QueueableEntity|QueueableCollection
    {
        if (is_array($data)) {
            $data = new ModelIdentifier($data['class'], $data['id'], $data['relations'], $data['connection']);

            if (isset($data['collectionClass'])) {
                $data->useCollectionClass($data['collectionClass']);
            }
        }

        return $this->getRestoredPropertyValue($data);
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof QueueableEntity
            || $data instanceof QueueableCollection;
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        if (! $this->supportsNormalization($object)) {
            throw new InvalidArgumentException(class_basename($this).' can only normalize queueable entities or collections.');
        }

        if (static::$allow_normalization) {
            return (array) $this->getSerializedPropertyValue($object);
        }

        throw new DoNotStoreModelsOnEventsOrStates($object);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            QueueableEntity::class => false,
            QueueableCollection::class => false,
        ];
    }

    protected function typeSupportsModelIdentifiers(string $type): bool
    {
        return is_a($type, QueueableEntity::class, true)
        || is_a($type, QueueableCollection::class, true);
    }

    protected function isDenormalizedModelIdentifier($denormalized): bool
    {
        return $denormalized instanceof ModelIdentifier
            || isset($denormalized['class'], $denormalized['id'], $denormalized['relations'], $denormalized['connection']);
    }
}
