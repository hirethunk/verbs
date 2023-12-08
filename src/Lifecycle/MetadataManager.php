<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Support\Collection;
use Thunk\Verbs\Event;
use WeakMap;

class MetadataManager
{
    protected WeakMap $ephemeral;

    public function __construct()
    {
        $this->ephemeral = new WeakMap();
    }

    public function setLastResults(Event $event, Collection $results): Event
    {
        return $this->setEphemeral($event, '_last_results', $results);
    }

    public function getLastResults(Event $event): Collection
    {
        return $this->getEphemeral($event, '_last_results', new Collection());
    }

    public function getEphemeral(Event $event, ?string $key = null, mixed $default = null): mixed
    {
        return data_get($this->ephemeral[$event] ?? [], $key, $default);
    }

    public function setEphemeral(Event $event, string $key, mixed $value): Event
    {
        $this->ephemeral[$event] ??= [];
        $this->ephemeral[$event][$key] = $value;

        return $event;
    }
}
