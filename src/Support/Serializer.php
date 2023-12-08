<?php

namespace Thunk\Verbs\Support;

use BackedEnum;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\Event;
use Thunk\Verbs\Metadata;
use Thunk\Verbs\Support\Normalization\BitsNormalizer;
use Thunk\Verbs\Support\Normalization\CarbonNormalizer;
use Thunk\Verbs\Support\Normalization\CollectionNormalizer;
use Thunk\Verbs\Support\Normalization\SelfSerializingNormalizer;
use Thunk\Verbs\Support\Normalization\StateNormalizer;

abstract class Serializer
{
    // FIXME: We need an API for normalizers
    public static array $custom_normalizers = [];

    public function __construct(
        public SymfonySerializer $serializer,
    ) {
    }

    public static function defaultSymfonySerializer(): SymfonySerializer
    {
        return new SymfonySerializer(
            normalizers: array_merge(self::$custom_normalizers, [
                new SelfSerializingNormalizer(),
                new CollectionNormalizer(),
                new StateNormalizer(),
                new BitsNormalizer(),
                new CarbonNormalizer(),
                new DateTimeNormalizer(),
                new BackedEnumNormalizer(),
                new ObjectNormalizer(
                    propertyTypeExtractor: new ReflectionExtractor(),
                    classDiscriminatorResolver: new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AnnotationLoader())),
                ),
            ]),
            encoders: [
                new JsonEncoder(),
            ],
        );
    }

    public function serialize(Event|Metadata $class): string
    {
        if (method_exists($class, '__sleep')) {
            $class = $class->__sleep();
        }

        return $this->serializer->serialize($class, 'json');
    }

    protected function unserialize($target, $data)
    {
        $type = $target;
        $context = [];

        if (is_object($target)) {
            $type = $target::class;
            $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $target;
        }

        // FIXME: Symfony's serializer is a little wonky. May need to re-think things.
        if (is_array($data)) {
            $data = array_map(fn ($value) => $value instanceof BackedEnum ? $value->value : $value, $data);
        }

        $callback = is_array($data) ? $this->serializer->denormalize(...) : $this->serializer->deserialize(...);

        return $callback(
            data: $data,
            type: $type,
            format: 'json',
            context: $context,
        );
    }
}
