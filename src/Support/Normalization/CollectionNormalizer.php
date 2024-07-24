<?php

namespace Thunk\Verbs\Support\Normalization;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Thunk\Verbs\SerializedByVerbs;

class CollectionNormalizer implements DenormalizerInterface, NormalizerInterface, SerializerAwareInterface
{
    use AcceptsNormalizerAndDenormalizer;

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_a($type, Collection::class, true);
    }

    /** @param  class-string<Collection>  $type */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Collection
    {
        $fqcn = data_get($data, 'fqcn', Collection::class);
        $items = data_get($data, 'items', []);
        $isAssoc = data_get($data, 'associative', false);

        if ($items === []) {
            return new $fqcn;
        }

        $subtype = data_get($data, 'type');
        if ($subtype === null) {
            throw new InvalidArgumentException('Cannot denormalize a Collection that has no type information.');
        }

        return $fqcn::make($items)
            ->when($isAssoc, fn ($collection) => $collection->mapWithKeys(fn ($value) => [$value[0] => $value[1]]))
            ->map(fn ($value) => $this->serializer->denormalize($value, $subtype, $format, $context));
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Collection;
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        if (! $object instanceof Collection) {
            throw new InvalidArgumentException(class_basename($this).' can only normalize Collection objects.');
        }

        return array_filter([
            'fqcn' => $object::class === Collection::class ? null : $object::class,
            'type' => $this->determineContainedType($object),
            'items' => Arr::isAssoc($object->all())
                ? $object->map(fn ($value, $key) => [$key, $this->serializer->normalize($value, $format, $context)])->values()->all()
                : $object->map(fn ($value) => $this->serializer->normalize($value, $format, $context))->values()->all(),
            'associative' => Arr::isAssoc($object->all()),
        ]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Collection::class => false];
    }

    protected function determineContainedType(Collection $collection): ?string
    {
        [$only_objects, $types] = $this->getCollectionMetadata($collection);

        if ($types->isEmpty()) {
            return null;
        }

        // If the whole collection contains one type, then we're golden
        if ($types->count() === 1) {
            return $types->first();
        }

        // If not, but it's all objects, we can look at each object's parent classes
        // and interfaces, and determine if they all extend something that implements
        // the `SerializedByVerbs` interface. If they do, then we can use that shared
        // ancestor as the type we use for serializing the whole collection.
        if ($only_objects) {
            $ancestor_types = $this->getSharedAncestorTypes($types);
            if ($ancestor_types->count() > 1 && $ancestor_types->contains(SerializedByVerbs::class)) {
                return $ancestor_types->first();
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Cannot serialize a %s containing mixed types (got %s).',
            class_basename($collection),
            $types->map(fn ($fqcn) => class_basename($fqcn))->implode(', '),
        ));
    }

    protected function getCollectionMetadata(Collection $collection): array
    {
        $only_objects = true;
        $types = new Collection;

        foreach ($collection as $value) {
            $only_objects = $only_objects && is_object($value);
            $types->push(get_debug_type($value));
        }

        return [$only_objects, $types->unique()];
    }

    protected function getSharedAncestorTypes(Collection $types)
    {
        return $types->reduce(function (Collection $common, string $fqcn) {
            $parents = collect([$fqcn])
                ->merge(class_parents($fqcn))
                ->merge(class_implements($fqcn))
                ->values()
                ->filter()
                ->unique();

            return $common->isEmpty() ? $parents : $parents->intersect($common);
        }, new Collection);
    }
}
