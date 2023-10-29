<?php

namespace Thunk\Verbs\Support;

use InvalidArgumentException;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\Event;
use Thunk\Verbs\Support\Normalizers\BitsNormalizer;
use Thunk\Verbs\Support\Normalizers\CarbonNormalizer;

class EventSerializer
{
    public static function defaultSymfonySerializer(): SymfonySerializer
    {
        return new SymfonySerializer(
            normalizers: [
                new BitsNormalizer(),
                new CarbonNormalizer(),
                new DateTimeNormalizer(),
                new ObjectNormalizer(propertyTypeExtractor: new ReflectionExtractor()),
            ],
            encoders: [
                new JsonEncoder(),
            ],
        );
    }

    public function __construct(
        public SymfonySerializer $serializer,
    ) {
    }

    public function serialize(Event $event): string
    {
        if (method_exists($event, '__sleep')) {
            $event->__sleep();
        }

        return $this->serializer->serialize($event, 'json');
    }

    /** @param  Event|class-string<Event>  $target */
    public function deserialize(
        Event|string $target,
        string|array $data,
    ): Event {
        if (! is_a($target, Event::class, true)) {
            throw new InvalidArgumentException(class_basename($this).'::deserialize must be passed an Event class.');
        }

        $method = is_array($data) ? 'denormalize' : 'deserialize';

        if ($target instanceof Event) {
            return $this->serializer->$method(
                data: $data,
                type: $target::class,
                format: 'json',
                context: [AbstractNormalizer::OBJECT_TO_POPULATE => $target],
            );
        }

        return $this->serializer->$method($data, $target, 'json');
    }
}
