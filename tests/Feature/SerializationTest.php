<?php

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Glhd\Bits\Snowflake;
use Illuminate\Support\Str;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\SerializedByVerbs;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Normalization\NormalizeToPropertiesAndClassName;
use Thunk\Verbs\Support\Serializer;

it('supports instantiation via an associative array', function () {
    $snowflake = Snowflake::make();
    $timestamp = now();
    $string = Str::random();

    $event1 = EventWithConstructorPromotion::make([
        'string' => $string,
        'snowflake' => $snowflake->id(),
        'timestamp' => $timestamp->jsonSerialize(),
    ]);

    $this->assertTrue($snowflake->is($event1->event->snowflake));
    $this->assertEquals($timestamp, $event1->event->timestamp);
    $this->assertEquals($string, $event1->event->string);

    $event2 = EventWithJustPublicProperties::make([
        'snowflake' => $snowflake->id(),
        'string' => $string,
        'timestamp' => $timestamp->jsonSerialize(),
    ]);

    $this->assertTrue($snowflake->is($event2->event->snowflake));
    $this->assertEquals($timestamp, $event2->event->timestamp);
    $this->assertEquals($string, $event2->event->string);
});

it('supports instantiation via an positional arguments', function () {
    $snowflake = Snowflake::make();
    $timestamp = now();
    $string = Str::random();

    $event1 = EventWithConstructorPromotion::make(
        $snowflake->id(),
        $timestamp->jsonSerialize(),
        $string,
    );

    $this->assertTrue($snowflake->is($event1->event->snowflake));
    $this->assertEquals($timestamp, $event1->event->timestamp);
    $this->assertEquals($string, $event1->event->string);
});

it('triggers an error when using positional arguments with an event that does not support them', function () {
    $snowflake = Snowflake::make();
    $timestamp = now();
    $string = Str::random();

    EventWithJustPublicProperties::make(
        $snowflake->id(),
        $timestamp->jsonSerialize(),
        $string,
    );
})->throws(InvalidArgumentException::class);

it('allows us to store a serializable class as a property', function () {
    expect(function () {
        EventWithDto::fire(
            dto: new DTO
        );
    })->not->toThrow(TypeError::class);
});

it('honors configured context', function () {
    $target = new class
    {
        public $is_public = 'public';

        protected $is_protected = 'protected';

        private $is_private = 'private';
    };

    config()->set('verbs.serializer_context', [
        PropertyNormalizer::NORMALIZE_VISIBILITY => PropertyNormalizer::NORMALIZE_PUBLIC,
    ]);

    expect(app(Serializer::class)->serialize($target))
        ->toBe('{"is_public":"public"}');

    app()->forgetInstance(Serializer::class);

    config()->set('verbs.serializer_context', [
        PropertyNormalizer::NORMALIZE_VISIBILITY => PropertyNormalizer::NORMALIZE_PROTECTED,
    ]);

    expect(app(Serializer::class)->serialize($target))
        ->toBe('{"is_protected":"protected"}');

    app()->forgetInstance(Serializer::class);

    config()->set('verbs.serializer_context', [
        PropertyNormalizer::NORMALIZE_VISIBILITY => PropertyNormalizer::NORMALIZE_PRIVATE,
    ]);

    expect(app(Serializer::class)->serialize($target))
        ->toBe('{"is_private":"private"}');

    app()->forgetInstance(Serializer::class);

    config()->set('verbs.serializer_context', [
        PropertyNormalizer::NORMALIZE_VISIBILITY => PropertyNormalizer::NORMALIZE_PUBLIC | PropertyNormalizer::NORMALIZE_PROTECTED | PropertyNormalizer::NORMALIZE_PRIVATE,
    ]);

    expect(app(Serializer::class)->serialize($target))
        ->toBe('{"is_public":"public","is_protected":"protected","is_private":"private"}');
});

test('serializer does not call constructor when deserializing', function () {
    $event = app(Serializer::class)
        ->deserialize(EventWithConstructor::class, []);

    expect($event->constructed)->toBe(false);
});

it('does not include the event ID in its payload', function () {
    $result = app(Serializer::class)->serialize(new class extends Event
    {
        public string $name = 'Demo';

        public function __construct()
        {
            $this->id = snowflake_id();
        }
    });

    expect($result)->toBe('{"name":"Demo"}');
});

it('does not include the state ID or last_event_id in its payload', function () {
    $result = app(Serializer::class)->serialize(new class extends State
    {
        public Bits|UuidInterface|AbstractUid|int|string|null $id = 123;

        public Bits|UuidInterface|AbstractUid|int|string|null $last_event_id = 123;

        public string $name = 'Demo';
    });

    expect($result)->toBe('{"name":"Demo"}');
});

it('allows us to store a serializable class(es) as a property', function () {
    $original_event = new EventWithPhpDocArray;

    $serialized_data = app(Serializer::class)->serialize($original_event);

    expect($serialized_data)->toBe('{"dto":{"fqcn":"DTO","foo":1},"dtos":[{"fqcn":"DTO","foo":1}]}');

    $deserialized_event = app(Serializer::class)->deserialize(EventWithPhpDocArray::class, $serialized_data);

    expect($deserialized_event->dto)->toBeInstanceOf(DTO::class)
        ->and($deserialized_event->dtos[0])->toBeInstanceOf(DTO::class);
});

test('deserializing restores promoted defaults for keys the payload omits', function () {
    // The realistic case: an event stored before `count` and `tags` were added
    // carries neither key. `newInstanceWithoutConstructor()` skips promoted
    // properties, so without the defaults both come back uninitialized.
    $event = app(Serializer::class)->deserialize(EventWithPromotedDefaults::class, ['name' => 'Demo']);

    expect($event->name)->toBe('Demo')
        ->and($event->count)->toBe(7)
        ->and($event->tags)->toBe(['a']);
});

test('deserializing restores promoted defaults from a JSON payload', function () {
    $event = app(Serializer::class)->deserialize(EventWithPromotedDefaults::class, '{"name":"Demo"}');

    expect($event->count)->toBe(7)
        ->and($event->tags)->toBe(['a']);
});

test('a payload value overrides a promoted default', function () {
    $event = app(Serializer::class)->deserialize(EventWithPromotedDefaults::class, [
        'name' => 'Demo',
        'count' => 99,
    ]);

    expect($event->count)->toBe(99);
});

test('promoted properties with no default are left uninitialized', function () {
    $event = app(Serializer::class)->deserialize(EventWithPromotedDefaults::class, ['count' => 1]);

    expect((new ReflectionProperty($event, 'name'))->isInitialized($event))->toBeFalse();
});

test('snapshot hydration restores promoted defaults', function () {
    $state = app(Serializer::class)->deserialize(StateWithPromotedDefaults::class, ['name' => 'Demo']);

    expect($state->name)->toBe('Demo')
        ->and($state->count)->toBe(7);
});

test('reading a stored event that predates a promoted property uses its default', function () {
    VerbEvent::create([
        'id' => snowflake_id(),
        'type' => EventWithPromotedDefaults::class,
        'data' => ['name' => 'Demo'],
    ]);

    expect(VerbEvent::sole()->event()->count)->toBe(7);
});

test('a nested DTO restores promoted defaults for keys the payload omits', function () {
    $event = app(Serializer::class)->deserialize(
        EventWithPromotedDto::class,
        '{"dto":{"fqcn":"DtoWithPromotedDefaults"}}',
    );

    expect($event->dto->label)->toBe('default-label');
});

test('a promoted default is not required data for a nested DTO', function () {
    expect(DtoWithPromotedDefaults::requiredDataForVerbsDeserialization())->toBe([]);
});

class EventWithPromotedDefaults extends Event
{
    public function __construct(
        public string $name,
        public int $count = 7,
        public array $tags = ['a'],
    ) {}
}

class StateWithPromotedDefaults extends State
{
    public function __construct(
        public string $name = '',
        public int $count = 7,
    ) {}
}

class DtoWithPromotedDefaults implements SerializedByVerbs
{
    use NormalizeToPropertiesAndClassName;

    public function __construct(public string $label = 'default-label') {}
}

class EventWithPromotedDto extends Event
{
    public DtoWithPromotedDefaults $dto;
}

class EventWithConstructorPromotion extends Event
{
    public function __construct(
        public Snowflake $snowflake,
        public CarbonInterface $timestamp,
        public string $string,
    ) {}
}

class EventWithJustPublicProperties extends Event
{
    public Snowflake $snowflake;

    public CarbonInterface $timestamp;

    public string $string;
}

class DTO implements SerializedByVerbs
{
    use NormalizeToPropertiesAndClassName;

    public int $foo = 1;
}

class EventWithDto extends Event
{
    public DTO $dto;
}

class EventWithConstructor extends Event
{
    public bool $constructed = false;

    public function __construct()
    {
        $this->constructed = true;
    }
}

class EventWithPhpDocArray extends Event
{
    public function __construct(
        public DTO $dto = new DTO,
        /** @var DTO[] $dtos */
        public array $dtos = [new DTO]
    ) {}
}
