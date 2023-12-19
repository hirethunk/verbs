<?php

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\Exceptions\DoNotStoreModelsOnEventsOrStates;
use Thunk\Verbs\Support\Normalization\ModelNormalizer;

it('throws an exception when trying to serialize a model', function () {
    $serializer = new SymfonySerializer(
        normalizers: [$normalizer = new ModelNormalizer()],
        encoders: [new JsonEncoder()],
    );

    $serializer->normalize(new class() extends Model
    {
    }, 'json');
})->throws(DoNotStoreModelsOnEventsOrStates::class);
