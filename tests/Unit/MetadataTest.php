<?php

use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Metadata;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Serializer;

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

it('round-trips metadata through the event store, reviving object values', function () {
    $now = now();

    Verbs::createMetadataUsing(fn () => [
        'int' => 1337,
        'string' => 'Hello world',
        'array' => ['one', 'two'],
        'carbon' => $now,
        'nested' => ['when' => $now->toImmutable()],
    ]);

    MetadataTestEvent::fire(name: 'Verbs');
    Verbs::commit();

    // Drop the callbacks so nothing can regenerate metadata in-process:
    // whatever we read back must have come from the database.
    Verbs::createMetadataUsing(null);

    $event = app(StoresEvents::class)->read()->first();

    expect($event->metadata('int'))->toBe(1337)
        ->and($event->metadata('string'))->toBe('Hello world')
        ->and($event->metadata('array'))->toBe(['one', 'two'])
        ->and($event->metadata('carbon'))->toBeInstanceOf($now::class)
        ->and($event->metadata('carbon')->equalTo($now))->toBeTrue()
        ->and($event->metadata('nested')['when'])->toBeInstanceOf(CarbonImmutable::class)
        ->and($event->metadata('nested')['when']->equalTo($now))->toBeTrue();
});

it('reads metadata rows written before type envelopes existed', function () {
    MetadataTestEvent::fire(name: 'Verbs');
    Verbs::commit();

    // Simulate a pre-envelope row: bare values only.
    VerbEvent::query()->update(['metadata' => '{"legacy":"value","when":"2024-01-01T00:00:00Z"}']);

    Verbs::createMetadataUsing(null);

    $event = app(StoresEvents::class)->read()->first();

    expect($event->metadata('legacy'))->toBe('value')
        ->and($event->metadata('when'))->toBe('2024-01-01T00:00:00Z');
});

it('surfaces the stored value when an envelope type no longer exists', function () {
    MetadataTestEvent::fire(name: 'Verbs');
    Verbs::commit();

    VerbEvent::query()->update([
        'metadata' => '{"gone":{"__verbs_type":"App\\\\LongGoneClass","value":"still here"}}',
    ]);

    Verbs::createMetadataUsing(null);

    expect(app(StoresEvents::class)->read()->first()->metadata('gone'))->toBe('still here');
});

it('round-trips state values in metadata via id reduction', function () {
    $id = snowflake_id();

    MetadataStateTestEvent::fire(state_id: $id);
    Verbs::commit();

    $state = MetadataStateTestState::load($id);

    Verbs::createMetadataUsing(fn () => ['actor' => $state]);
    MetadataTestEvent::fire(name: 'with-state');
    Verbs::commit();

    Verbs::createMetadataUsing(null);

    // States in metadata store as their id (StateNormalizer's reduction), so
    // reading them back resolves through the identity map to the live instance.
    $stored = VerbEvent::query()->latest('id')->first();

    expect($stored->metadata['actor']['value'])->toBe((string) $id)
        ->and($stored->metadata()->actor)->toBe($state);
});

it('serializes an empty metadata bag to an empty JSON object', function () {
    expect(app(Serializer::class)->serializeMetadata(new Metadata))->toBe('{}');
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

class MetadataStateTestState extends State
{
    public int $count = 0;
}

class MetadataStateTestEvent extends Event
{
    #[StateId(MetadataStateTestState::class)]
    public int $state_id;

    public function apply(MetadataStateTestState $state): void
    {
        $state->count++;
    }
}

class MetadataTestMetadata extends Metadata
{
    public string $custom;
}
