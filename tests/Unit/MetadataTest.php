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

it('exposes metadata via multiple apis', function () {
    $meta = new MetadataTestMetadata(['custom' => 'set via construct', 'virtual' => 'also set via construct']);

    expect($meta)
        ->custom->toBe('set via construct')
        ->virtual->toBe('also set via construct')
        ->get('custom')->toBe('set via construct')
        ->get('virtual')->toBe('also set via construct')
        ->and($meta['custom'])->toBe('set via construct')
        ->and($meta['virtual'])->toBe('also set via construct');

    $meta->custom = 'set via prop';
    $meta->virtual = 'also set via prop';

    expect($meta)
        ->custom->toBe('set via prop')
        ->virtual->toBe('also set via prop')
        ->get('custom')->toBe('set via prop')
        ->get('virtual')->toBe('also set via prop')
        ->and($meta['custom'])->toBe('set via prop')
        ->and($meta['virtual'])->toBe('also set via prop');

    $meta['custom'] = 'set via array access';
    $meta['virtual'] = 'also set via array access';

    expect($meta)
        ->custom->toBe('set via array access')
        ->virtual->toBe('also set via array access')
        ->get('custom')->toBe('set via array access')
        ->get('virtual')->toBe('also set via array access')
        ->and($meta['custom'])->toBe('set via array access')
        ->and($meta['virtual'])->toBe('also set via array access');

    $meta->put('custom', 'set via put function');
    $meta->put('virtual', 'also set via put function');

    expect($meta)
        ->custom->toBe('set via put function')
        ->virtual->toBe('also set via put function')
        ->get('custom')->toBe('set via put function')
        ->get('virtual')->toBe('also set via put function')
        ->and($meta['custom'])->toBe('set via put function')
        ->and($meta['virtual'])->toBe('also set via put function');
});

it('lets you set metadata on an event', function () {
    $event = new MetadataTestEvent('demo');
    $event->metadata()->put('foo', 'bar')->put('baz', 'foo');

    expect($event)
        ->metadata('foo')->toBe('bar')
        ->metadata('baz')->toBe('foo');
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

class MetadataTestMetadata extends Metadata
{
    public string $custom;
}
