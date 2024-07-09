<?php

use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Contracts\Queue\QueueableEntity;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\Exceptions\DoNotStoreModelsOnEventsOrStates;
use Thunk\Verbs\Support\Normalization\ModelNormalizer;

it('throws an exception when trying to serialize a model', function () {
    $serializer = new SymfonySerializer(
        normalizers: [new ModelNormalizer()],
        encoders: [new JsonEncoder()],
    );

    $serializer->normalize(new ModelNormalizerTestModel(), 'json');
})->throws(DoNotStoreModelsOnEventsOrStates::class);

it('denormalizes models', function () {
    $serializer = new SymfonySerializer(
        normalizers: [$normalizer = new ModelNormalizer()],
        encoders: [new JsonEncoder()],
    );

    $identifier = new ModelIdentifier(
        class: ModelNormalizerTestModel::class,
        id: 1,
        relations: [],
        connection: null,
    );

    $identifier_array = [
        'class' => ModelNormalizerTestModel::class,
        'id' => 1,
    ];

    expect($normalizer->supportsDenormalization($identifier, ModelNormalizerTestModel::class))->toBeTrue()
        ->and($normalizer->supportsDenormalization($identifier_array, ModelNormalizerTestModel::class))->toBeTrue();

    $result = $serializer->denormalize($identifier, ModelNormalizerTestModel::class, 'json');

    expect($result->source)->toBe(ModelNormalizerTestModel::class);

    $result = $serializer->denormalize($identifier_array, ModelNormalizerTestModel::class, 'json');

    expect($result->source)->toBe(ModelNormalizerTestModel::class);
});

it('normalizes models when forced to do so', function () {
    $serializer = new SymfonySerializer(
        normalizers: [$normalizer = new ModelNormalizer()],
        encoders: [new JsonEncoder()],
    );

    ModelNormalizer::dangerouslyAllowModelNormalization();

    $model = new ModelNormalizerTestModel(['id' => 1337]);

    expect($normalizer->supportsNormalization($model))->toBeTrue();

    $normalized = $serializer->normalize($model, 'json');

    expect($normalized)->toMatchArray([
        'class' => ModelNormalizerTestModel::class,
        'id' => 1337,
    ]);

    $serialized = $serializer->serialize($model, 'json');

    expect($serialized)->toBe('{"class":"ModelNormalizerTestModel","id":1337}');
});

class ModelNormalizerTestModel extends Model
{
    public function newQueryForRestoration($ids)
    {
        return new class implements QueueableEntity
        {
            public string $source = ModelNormalizerTestModel::class;

            public function __call(string $name, array $arguments)
            {
                return $this;
            }

            public function getQueueableId() {}

            public function getQueueableRelations() {}

            public function getQueueableConnection() {}
        };
    }
}
