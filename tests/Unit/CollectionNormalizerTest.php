<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\SerializedByVerbs;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Normalization\CarbonNormalizer;
use Thunk\Verbs\Support\Normalization\CollectionNormalizer;
use Thunk\Verbs\Support\Normalization\NormalizeToPropertiesAndClassName;
use Thunk\Verbs\Support\Normalization\SelfSerializingNormalizer;
use Thunk\Verbs\Support\Normalization\StateNormalizer;

it('can normalize an empty collection', function () {
    $serializer = new SymfonySerializer(
        normalizers: [$normalizer = new CollectionNormalizer()],
        encoders: [new JsonEncoder()],
    );

    $collection = new Collection();

    expect($normalizer->supportsNormalization($collection))->toBeTrue();

    $normalized = $serializer->normalize($collection, 'json');
    expect($normalized)->toBe([]);

    $encoded = json_encode($normalized);
    expect($encoded)->toBe('[]')
        ->and($normalizer->supportsDenormalization($encoded, Collection::class, 'json'))->toBeTrue();

    $denormalized = $serializer->denormalize($normalized, Collection::class);

    // And the denormalized data should be the same
    expect($denormalized)->toBeInstanceOf(Collection::class)
        ->and($denormalized->isEmpty())->toBeTrue();
});

it('can normalize an empty Eloquent collection', function () {
    $serializer = new SymfonySerializer(
        normalizers: [$normalizer = new CollectionNormalizer()],
        encoders: [new JsonEncoder()],
    );

    $collection = new EloquentCollection();

    expect($normalizer->supportsNormalization($collection))->toBeTrue();

    $normalized = $serializer->normalize($collection, 'json');
    expect($normalized)->toBe(['fqcn' => EloquentCollection::class]);

    $encoded = json_encode($normalized);
    expect($encoded)->toBe('{"fqcn":'.json_encode(EloquentCollection::class).'}')
        ->and($normalizer->supportsDenormalization($encoded, EloquentCollection::class, 'json'))->toBeTrue();

    $denormalized = $serializer->denormalize($normalized, EloquentCollection::class);

    // And the denormalized data should be the same
    expect($denormalized)->toBeInstanceOf(EloquentCollection::class)
        ->and($denormalized->isEmpty())->toBeTrue();
});

it('can normalize a collection all of scalars', function () {
    $collections = [
        [Collection::make([1, 2, 3]), '{"type":"int","items":[1,2,3]}'],
        [Collection::make([1.5, 2.2, 3.99]), '{"type":"float","items":[1.5,2.2,3.99]}'],
        [Collection::make(['1', '2', '3']), '{"type":"string","items":["1","2","3"]}'],
        [Collection::make([false, true, true, false, false, true]), '{"type":"bool","items":[false,true,true,false,false,true]}'],
    ];

    $serializer = new SymfonySerializer(
        normalizers: [$normalizer = new CollectionNormalizer()],
        encoders: [new JsonEncoder()],
    );

    foreach ($collections as $iteration) {
        [$collection, $expected_json] = $iteration;

        // We should be able to normalize
        expect($normalizer->supportsNormalization($collection))->toBeTrue();
        $normalized = $serializer->normalize($collection, 'json');

        // And encode to JSON
        $encoded = json_encode($normalized);
        expect($encoded)->toBe($expected_json);

        // And then denormalize that JSON
        expect($normalizer->supportsDenormalization($encoded, Collection::class, 'json'))->toBeTrue();
        $denormalized = $serializer->denormalize(json_decode($encoded), Collection::class);

        // And the denormalized data should be the same
        expect($denormalized)->toBeInstanceOf(Collection::class);
        expect($denormalized->all())->toBe($collection->all());
    }
});

it('can normalize a collection with keys and restore key order', function () {
    $collection = Collection::make([
        2 => 2,
        1 => 1,
        3 => 3,
    ]);

    $expected_json = '{"type":"int","items":[[2,2],[1,1],[3,3]],"associative":true}';

    $serializer = new SymfonySerializer(
        normalizers: [$normalizer = new CollectionNormalizer()],
        encoders: [new JsonEncoder()],
    );

    // We should be able to normalize
    expect($normalizer->supportsNormalization($collection))->toBeTrue();
    $normalized = $serializer->normalize($collection, 'json');

    // And encode to JSON
    $encoded = json_encode($normalized);
    expect($encoded)->toBe($expected_json);

    // And then denormalize that JSON
    expect($normalizer->supportsDenormalization($encoded, Collection::class, 'json'))->toBeTrue();
    $denormalized = $serializer->denormalize(json_decode($encoded), Collection::class);

    // And the denormalized data should be the same
    expect($denormalized)->toBeInstanceOf(Collection::class);
    expect($denormalized->all())->toBe($collection->all());
});

it('can normalize a collection all of states', function () {
    $manager = app(StateManager::class);

    $serializer = new SymfonySerializer(
        normalizers: [
            $normalizer = new CollectionNormalizer(),
            new StateNormalizer(),
            new PropertyNormalizer(propertyTypeExtractor: new ReflectionExtractor()),
        ],
        encoders: [
            new JsonEncoder(),
        ],
    );

    $collection = Collection::make([
        $first = $manager->register(CollectionNormalizerTestState::make(label: 'First State')),
        $second = $manager->register(CollectionNormalizerTestState::make(label: 'Second State')),
    ]);

    expect($normalizer->supportsNormalization($collection))->toBeTrue();

    $normalized = $serializer->serialize($collection, 'json');

    expect($normalized)->not->toContain('"fqcn"')
        ->toContain('"type":"CollectionNormalizerTestState"')
        ->toContain('"items":["');

    $denormalized = $serializer->deserialize($normalized, Collection::class, 'json');

    expect($denormalized)->toBeInstanceOf(Collection::class)
        ->and($denormalized->shift())->toBe($first)
        ->and($denormalized->shift())->toBe($second);
});

it('can normalize collections of objects that implement SerializedByVerbs', function () {
    $serializer = new SymfonySerializer(
        normalizers: [
            $normalizer = new CollectionNormalizer(),
            new CarbonNormalizer(),
            new SelfSerializingNormalizer(),
            new PropertyNormalizer(propertyTypeExtractor: new ReflectionExtractor()),
        ],
        encoders: [
            new JsonEncoder(),
        ],
    );

    $collection = Collection::make([
        $parent = new CollectionNormalizerTestDataObject('hello', 42, now()->toImmutable(), ['a', 'b', 'c']),
        $child = new CollectionNormalizerTestChildDataObject('world', 21, now()->subDay()->toImmutable(), ['c', 'b', 'a'], false),
    ]);

    expect($normalizer->supportsNormalization($collection))->toBeTrue();

    $normalized = $serializer->serialize($collection, 'json');

    expect($normalized)->toContain('"type":"CollectionNormalizerTestDataObject"');

    $denormalized = $serializer->deserialize($normalized, Collection::class, 'json');

    $denormalized_parent = $denormalized->shift();
    expect($denormalized_parent->string)->toBe($parent->string)
        ->and($denormalized_parent->int)->toBe($parent->int)
        ->and($parent->carbon->eq($denormalized_parent->carbon))->toBeTrue()
        ->and($denormalized_parent->array)->toBe($parent->array);

    $denormalized_child = $denormalized->shift();
    expect($denormalized_child->string)->toBe($child->string)
        ->and($denormalized_child->int)->toBe($child->int)
        ->and($child->carbon->eq($denormalized_child->carbon))->toBeTrue()
        ->and($denormalized_child->array)->toBe($child->array)
        ->and($denormalized_child->bool)->toBe($child->bool);
});

class CollectionNormalizerTestState extends State
{
    public string $label;
}

class CollectionNormalizerTestDataObject implements SerializedByVerbs
{
    use NormalizeToPropertiesAndClassName;

    public function __construct(
        public string $string,
        public int $int,
        public CarbonImmutable $carbon,
        public array $array,
    ) {}
}

class CollectionNormalizerTestChildDataObject extends CollectionNormalizerTestDataObject
{
    public function __construct(
        string $string,
        int $int,
        CarbonImmutable $carbon,
        array $array,
        public bool $bool,
    ) {
        parent::__construct($string, $int, $carbon, $array);
    }
}
