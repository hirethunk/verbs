<?php

namespace Thunk\Verbs\Livewire;

use Livewire\ComponentHook;
use Livewire\Livewire;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\BrokerBuilder;
use Thunk\Verbs\Lifecycle\BrokerStore;

use function Livewire\on;

class SupportVerbs extends ComponentHook
{
    public static function provide()
    {
        Livewire::propertySynthesizer([
            PendingEventSynth::class,
            StateSynth::class,
        ]);
        on('request', static::request(...));
        on('response', static::response(...));
    }

    public function render()
    {
        Verbs::commit();
    }

    public static function request(\Illuminate\Http\Request $request)
    {
        $verbs = $request->get('verbs');

        if (! $verbs) {
            return;
        }

        if (! app(BrokerStore::class)->has('standalone')) {
            app(BrokerStore::class)->register('standalone', BrokerBuilder::standalone());
        }

        app(BrokerStore::class)->get('standalone')->event_store->hydrate(
            data: json_decode(
                data_get($verbs, 'eventsEncoded', '[]'),
                true
            ),
        );
    }

    public static function response()
    {
        return function (&$response) {
            $eventData = app(BrokerStore::class)->get('standalone')->event_store->dehydrate();

            $eventData['eventsEncoded'] = json_encode($eventData);

            $response['verbs'] = array_merge(
                $response['verbs'] ?? [],
                $eventData,
            );
        };
    }
}
