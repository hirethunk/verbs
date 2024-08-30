<?php

namespace Thunk\Verbs\Support\Normalization;

use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Thunk\Verbs\ShouldMigrateData;
use Thunk\Verbs\Support\Migrator;

class MigrationNormalizer implements DenormalizerInterface, NormalizerInterface, SerializerAwareInterface
{
    use AcceptsNormalizerAndDenormalizer;

    public function normalize($object, ?string $format = null, array $context = []): array
    {
        if (! $object instanceof ShouldMigrateData) {
            throw new InvalidArgumentException(class_basename($this).' can only normalize classes that implement ShouldMigrateData.');
        }

        $reflect = new ReflectionClass($object);
        if ($reflect->hasProperty('__vn')) {
            throw new RuntimeException('NormalizeToPropertiesAndClass cannot serialize objects with a "__vn" property.');
        }

        $context['migrated'] = true;

        $data = $this->serializer->normalize($object, $format, $context);

        $data['__vn'] = max(array_keys($object->migrations()));

        return $data;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        if (! $data instanceof ShouldMigrateData) {
            return false;
        }

        $alreadyMigrated = $context['migrated'] ?? false;

        return ! $alreadyMigrated;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ShouldMigrateData::class => false,
        ];
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (is_a($data, $type)) {
            return $data;
        }

        $reflect = new ReflectionClass($type);

        /** @var ShouldMigrateData $instance */
        $instance = $reflect->newInstanceWithoutConstructor();

        $data = Migrator::migrate($instance, $data);
        $data = \Arr::except($data, ['__vn']);

        $context['migrated'] = true;

        return $this->serializer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        $hasMigrated = $context['migrated'] ?? false;

        return ! $hasMigrated && is_a($type, ShouldMigrateData::class, true);
    }
}
