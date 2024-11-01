<?php

namespace Thunk\Verbs\Support\Normalization;

use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Thunk\Verbs\Event;

class EventMigrationNormalizer implements DenormalizerInterface, NormalizerInterface, SerializerAwareInterface
{
    use AcceptsNormalizerAndDenormalizer;

    public function normalize($object, ?string $format = null, array $context = []): array
    {
        if (!$object instanceof Event) {
            throw new InvalidArgumentException(class_basename($this) . ' can only normalize Events');
        }

        $context['migrated'] = true;

        return $this->serializer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        if (!$data instanceof Event) {
            return false;
        }

        $alreadyMigrated = $context['migrated'] ?? false;

        return !$alreadyMigrated;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Event::class => false,
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

        /** @var Event $instance */
        $instance = $reflect->newInstanceWithoutConstructor();

        if (method_exists($instance, 'migrate')) {
            $migrations = $instance::migrate();
            $data = self::migrate($migrations, $data);
        }

        $context['migrated'] = true;

        return $this->serializer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        $hasMigrated = $context['migrated'] ?? false;

        return !$hasMigrated && is_a($type, Event::class, true);
    }

    private static function migrate(array $migrations, array $data): array
    {
        $collection = collect($data);

        foreach ($migrations as $migration) {
            if (is_callable($migration)) {
                $collection = $migration($collection);
            }
        }

        return $collection->all();
    }
}
