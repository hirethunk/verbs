<?php

namespace Thunk\Verbs\Examples\Monopoly\Game;

use Illuminate\Support\Collection;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Space;
use Thunk\Verbs\SerializedByVerbs;

class SpaceCollection extends Collection implements SerializedByVerbs
{
    public static function deserializeForVerbs(mixed $data, DenormalizerInterface $denormalizer): static
    {
        return static::make($data)
            ->map(fn ($serialized) => Space::deserializeForVerbs($serialized, $denormalizer));
    }

    public function serializeForVerbs(NormalizerInterface $normalizer): string|array
    {
        return $this->map(fn (Space $space) => $space->serializeForVerbs($normalizer))->toJson();
    }
}
