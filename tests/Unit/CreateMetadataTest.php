<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\EventStore;
use Thunk\Verbs\Metadata;
use Thunk\Verbs\Models\VerbEvent;

it('creates metadata for events', function () {
    EventStore::createMetadataUsing(function (Metadata $metadata) {
        $metadata->initiator_id = 888888;

        return $metadata;
    });

    $event = MetadataTestEvent::make(name: 'Verbs');
    $event->event->id = 1;

    $event->fire();

    EventStore::createMetadataUsing(function (Metadata $metadata) {
        $metadata->request_id = 'abc';

        return $metadata;
    });

    expect(HandleChecker::$handled)->toBeFalse();

    Verbs::commit();

    $event = VerbEvent::sole();
    expect($event->data)->toMatchArray([
        'name' => 'Verbs',
        'id' => 1,
    ])->and($event->metadata)->toMatchArray([
        'request_id' => 'abc',
        'initiator_id' => 888888,
    ])
        ->and($event->metadata())->toBeInstanceOf(Metadata::class)
        ->and(HandleChecker::$handled)->toBeTrue();
});

class MetadataTestEvent extends Event
{
    public function __construct(
        public string $name
    ) {
    }

    public function handle(Metadata $metadata)
    {
        HandleChecker::$handled = $metadata->request_id === 'abc' && $metadata->initiator_id === 888888;
    }
}

class HandleChecker
{
    public static bool $handled = false;
}
