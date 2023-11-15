<?php

use Illuminate\Support\Collection;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Normalization\CollectionNormalizer;
use Thunk\Verbs\Support\Normalization\StateNormalizer;

it('it can normalize a collection all of scalars', function() {
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

it('it can normalize a collection all of states', function() {
	$manager = app(StateManager::class);
	
	$serializer = new SymfonySerializer(
		normalizers: [
			$normalizer = new CollectionNormalizer(),
			new StateNormalizer(),
			new ObjectNormalizer(propertyTypeExtractor: new ReflectionExtractor()),
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

class CollectionNormalizerTestState extends State
{
	public string $label;
}
