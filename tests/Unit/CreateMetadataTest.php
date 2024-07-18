<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Metadata;
use Thunk\Verbs\Models\VerbEvent;

it('creates metadata for events', function () {
    // Test both the `Metadata` class API
    Verbs::createMetadataUsing(function (Metadata $metadata) {
        $metadata->initiator_id = 888888;
    });

    // And the array shorthand API
    Verbs::createMetadataUsing(fn (Metadata $metadata) => ['request_id' => 'abc']);

    $event = MetadataTestEvent::make(name: 'Verbs');
    $event->event->id = 1;
    $event->fire();

    expect(HandleChecker::$handled)->toBeFalse();

    Verbs::commit();

    $model = VerbEvent::sole();

    expect($model->data)->toMatchArray(['name' => 'Verbs'])
        ->and($model->metadata)->toMatchArray(['request_id' => 'abc', 'initiator_id' => 888888])
        ->and($model->metadata())->toBeInstanceOf(Metadata::class)
        ->and($model->event()->metadata())->toBeInstanceOf(Metadata::class)
        ->and($model->event()->metadata('request_id'))->toBe('abc')
        ->and($model->event()->metadata('initiator_id'))->toBe(888888)
        ->and($model->event()->metadata('foo'))->toBeNull()
        ->and($model->event()->metadata('bar', 'baz'))->toBe('baz')
        ->and(HandleChecker::$handled)->toBeTrue();
});

class MetadataTestEvent extends Event
{
    public function __construct(
        public string $name
    ) {}

    public function handle(Metadata $metadata)
    {
        HandleChecker::$handled = $metadata->request_id === 'abc' && $metadata->initiator_id === 888888;
    }
}

class HandleChecker
{
    public static bool $handled = false;
}
