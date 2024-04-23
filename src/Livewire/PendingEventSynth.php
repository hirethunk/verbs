<?php

namespace Thunk\Verbs\Livewire;

use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;
use Thunk\Verbs\Lifecycle\BrokerStore;
use Thunk\Verbs\Models\VerbEvent;
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
        $eventData = app(BrokerStore::class)->get('standalone')->event_store->formatEventForWrite($target->event);

        $data = json_decode($eventData['data']);

        unset($eventData['data'], $eventData['event'], $eventData['metadata_raw']);

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

        $model = VerbEvent::make($eventData);
        app(BrokerStore::class)->get('standalone')->metadata->set($model->event(), $model->metadata());

        $event = $model->event();

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
