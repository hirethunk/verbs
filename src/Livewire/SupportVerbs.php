<?php

namespace Thunk\Verbs\Livewire;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\ComponentHook;
use Livewire\Drawer\Utils;
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

        Route::get('/verbs/verbs-livewire.js', [static::class, 'returnJavaScriptAsFile']);

        Blade::directive('verbsScripts', [static::class, 'verbsScripts']);
    }

    public static function verbsScripts($expression)
    {
        $class = static::class;

        return "{!! {$class}::scripts({$expression}) !!}";
    }

    public static function scripts()
    {
        $url = '/verbs/verbs-livewire.js';

        $broker = app(BrokerStore::class)->get('standalone');

        $events_string = \Livewire\Drawer\Utils::escapeStringForHtml(
            $broker->event_store->dehydrate()
        );

        return <<<HTML
        <script src="{$url}" verbs:events="{$events_string}"></script>
        HTML;
    }

    public function returnJavaScriptAsFile()
    {
        return Utils::pretendResponseIsFile(
            __DIR__.'/verbs-livewire.js'
        );
    }

    public function render()
    {
        Verbs::commit();
    }

    public static function request()
    {
        $verbs = request()->get('verbs');

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
            $broker = app(BrokerStore::class)->get('standalone');

            if (! $broker) {
                return;
            }

            $eventData = $broker->event_store->dehydrate();

            $eventData['eventsEncoded'] = json_encode($eventData);

            $response['verbs'] = array_merge(
                $response['verbs'] ?? [],
                $eventData,
            );
        };
    }
}
