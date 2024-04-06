<?php

namespace Thunk\Verbs\Livewire;

use Livewire\ComponentHook;
use Livewire\Livewire;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\EphemeralEventQueue;

use function Livewire\on;

class SupportVerbs extends ComponentHook
{
    public static function provide()
    {
        Livewire::propertySynthesizer(PendingEventSynth::class);
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

        app(EphemeralEventQueue::class)->hydrate($verbs['events'] ?? []);
    }

    public static function response()
    {
        return function (&$response) {
            $response['verbs']['events'] = app(EphemeralEventQueue::class)->dehydrate();
        };
    }
}
