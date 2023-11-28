<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\EventStore;
use Thunk\Verbs\Metadata;
use Thunk\Verbs\Models\VerbEvent;

it('creates metadata for events', function () {
    EventStore::createMetadataUsing(function (Metadata $metadata) {
        $metadata->initiator_id = 888888;

        return $metadata;
    });

    $store = app(EventStore::class);

    $event = new MetadataTestEvent(name: 'Verbs');
    $event->id = 1;

    EventStore::createMetadataUsing(function (Metadata $metadata) {
        $metadata->request_id = 'abc';

        return $metadata;
    });
    $store->write([$event]);

    $event = VerbEvent::sole();
    expect($event->data)->toMatchArray([
        'name' => 'Verbs',
        'id' => 1,
    ])->and($event->metadata)->toMatchArray([
        'request_id' => 'abc',
        'initiator_id' => 888888,
    ])
        ->and($event->metadata())->toBeInstanceOf(Metadata::class);
});

class MetadataTestEvent extends Event
{
    public function __construct(
        public string $name
    ) {
    }
}
