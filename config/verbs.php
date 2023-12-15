<?php

use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Thunk\Verbs\Support\Normalization\BitsNormalizer;
use Thunk\Verbs\Support\Normalization\CarbonNormalizer;
use Thunk\Verbs\Support\Normalization\CollectionNormalizer;
use Thunk\Verbs\Support\Normalization\SelfSerializingNormalizer;
use Thunk\Verbs\Support\Normalization\StateNormalizer;

return [
    'normalizers' => [
        SelfSerializingNormalizer::class,
        CollectionNormalizer::class,
        StateNormalizer::class,
        BitsNormalizer::class,
        CarbonNormalizer::class,
        DateTimeNormalizer::class,
        BackedEnumNormalizer::class,
        PropertyNormalizer::class,
    ],
];
