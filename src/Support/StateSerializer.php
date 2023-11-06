<?php

namespace Thunk\Verbs\Support;

use InvalidArgumentException;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Normalizers\BitsNormalizer;
use Thunk\Verbs\Support\Normalizers\CarbonNormalizer;
use Thunk\Verbs\Support\Normalizers\SelfSerializingNormalizer;
use Thunk\Verbs\Support\Normalizers\StateNormalizer;

class StateSerializer
{
    public static function defaultSymfonySerializer(): SymfonySerializer
    {
        return new SymfonySerializer(
            normalizers: [
                // new StateNormalizer(),
                new SelfSerializingNormalizer(),
                new BackedEnumNormalizer(),
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

    public function serialize(State $state): string
    {
        if (method_exists($state, '__sleep')) {
            $state->__sleep();
        }

        return $this->serializer->serialize($state, 'json');
    }

    /** @param  State|class-string<State>  $target */
    public function deserialize(
        State|string $target,
        string|array $data,
    ): State {
        if (! is_a($target, State::class, true)) {
            throw new InvalidArgumentException(class_basename($this).'::deserialize must be passed a State class.');
        }

        $type = $target;
        $context = [];

        if ($target instanceof State) {
            $type = $target::class;
            $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $target;
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
