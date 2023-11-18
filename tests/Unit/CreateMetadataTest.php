<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\EventStore;
use Thunk\Verbs\Models\VerbEvent;

it('creates metadata for events', function () {
    EventStore::createMetadataUsing(fn() => ['initiator_id' => 888888]);

    $store = app(EventStore::class);

    $event = new MetadataTestEvent(name: 'Verbs');
    $event->id = 1;

    EventStore::createMetadataUsing(fn() => ['request_id' => 'abc']);
    $store->write([$event]);

    expect(VerbEvent::firstOrFail()->data)->toMatchArray([
        'name' => 'Verbs',
        'id' => 1,
        'metadata' => [
            'request_id' => 'abc',
            'initiator_id' => 888888
        ]
    ]);
});

class MetadataTestEvent extends Event
{
    public function __construct(
        public string $name
    ){}
}
