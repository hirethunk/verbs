<?php

namespace Thunk\Verbs\Support;

use InvalidArgumentException;
use Thunk\Verbs\Metadata;

class MetadataSerializer extends Serializer
{
    /** @param  Metadata|class-string<Metadata>  $target */
    public function deserialize(
        Metadata|string $target,
        string|array $data,
    ): Metadata {
        if (! is_a($target, Metadata::class, true)) {
            throw new InvalidArgumentException(class_basename($this).'::deserialize must be passed a Metadata class.');
        }

        return $this->unserialize($target, $data);
    }
}
