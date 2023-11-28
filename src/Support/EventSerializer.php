<?php

namespace Thunk\Verbs\Support;

use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Thunk\Verbs\Event;

class EventSerializer extends Serializer
{
    /** @param  Event|class-string<Event>  $target */
    public function deserialize(
        Event|string $target,
        string|array $data,
    ): Event {
        if (! is_a($target, Event::class, true)) {
            throw new InvalidArgumentException(class_basename($this).'::deserialize must be passed an Event class.');
        }

        $type = $target;
        $context = [];

        if ($target instanceof Event) {
            $type = $target::class;
            $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $target;
        }

        return parent::unserialize($data, $type, $context);
    }
}
