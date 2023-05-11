<?php

namespace Thunk\Verbs\Lifecycle;

use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\Contracts\SerializesAndRestoresEvents;
use Thunk\Verbs\Event;

class Serializer implements SerializesAndRestoresEvents
{
    public function __construct(
        protected SymfonySerializer $serializer
    ) {
    }

    public function serializeEvent(Event $event): string
    {
        return $this->serializer->serialize($event, 'json', [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['id', 'context_id'],
        ]);
    }

    public function deserializeEvent(string $event_type, string $data): Event
    {
        return $this->serializer->deserialize($data, $event_type, 'json');
    }
}
