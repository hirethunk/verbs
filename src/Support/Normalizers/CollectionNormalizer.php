<?php

namespace Thunk\Verbs\Support\Normalizers;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class CollectionNormalizer implements DenormalizerInterface, NormalizerInterface, SerializerAwareInterface
{
	protected NormalizerInterface|DenormalizerInterface $serializer;
	
	public function setSerializer(SerializerInterface $serializer)
	{
		if ($serializer instanceof NormalizerInterface && $serializer instanceof DenormalizerInterface) {
			$this->serializer = $serializer;
			return;
		}
		
		throw new InvalidArgumentException('The CollectionNormalizer expects a serializer that implements both normalization and denormalization.');
	}
	
	public function supportsDenormalization(mixed $data, string $type, string $format = null): bool
	{
		return is_a($type, Collection::class, true)
			&& is_array($data)
			&& ([] === $data || isset($data['type'], $data['items']));
	}
	
	/** @param class-string<Collection> $type */
	public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Collection
	{
		$fqcn = $data['fqcn'] ?? Collection::class;
		$items = $data['items'] ?? [];
		$subtype = $data['type'] ?? null;
		
		if ($items === []) {
			return new $fqcn;
		}
		
		if ($subtype === null) {
			throw new InvalidArgumentException('Cannot denormalize a Collection that has no type information.');
		}
		
		return $fqcn::make($items)->map(fn($value) => $this->serializer->denormalize($value, $subtype));
	}
	
	public function supportsNormalization(mixed $data, string $format = null): bool
	{
		return $data instanceof Collection;
	}
	
	public function normalize(mixed $object, string $format = null, array $context = []): array
	{
		if (! $object instanceof Collection) {
			throw new InvalidArgumentException(class_basename($this).' can only normalize Carbon objects.');
		}
		
		$types = $object->map(fn($value) => get_debug_type($value))->unique();
		if ($types->count() > 1) {
			throw new InvalidArgumentException('Cannot serialize a Collection containing mixed types.');
		}
		
		return array_filter([
			'fqcn' => $object::class === Collection::class ? null : $object::class,
			'type' => $types->first(),
			'items' => $object->map(fn($value) => $this->serializer->normalize($value, $format, $context))->all(),
		]);
	}
	
	public function getSupportedTypes(?string $format): array
	{
		return [
			Collection::class => false,
		];
	}
}
