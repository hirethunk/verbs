<?php

namespace Thunk\Verbs\Livewire;

use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Support\PendingEvent;

class PendingEventSynth extends Synth
{
    public static $key = 'VrbPE';

    public static function match($target)
    {
        return $target instanceof PendingEvent;
    }

    public function dehydrate(PendingEvent $target)
    {
        $eventData = app(StoresEvents::class)->writeEphemeral([$target->event])[0];

        $data = json_decode($eventData['data']);

        unset($eventData['data']);

        $meta = $eventData;

        return [
            $data,
            $meta,
        ];
    }

    public function hydrate($data, $meta)
    {
        $eventData = $meta;

        $eventData['data'] = json_encode($data);

        $event = app(StoresEvents::class)->readEphemeral([$eventData])->first();

        return new PendingEvent($event);
    }

    public function get(&$target, $key)
    {
        throw new \Exception('Cannot get pending event properties directly.');
    }

    public function set(&$target, $key, $value)
    {
        data_set($target, "event.{$key}", $value);
    }

    public function call(&$target, $method, $params, $addEffect)
    {
        throw new \Exception('Cannot call pending event methods directly.');
    }
}
