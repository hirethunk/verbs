<?php

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Glhd\Bits\Snowflake;
use Illuminate\Support\Str;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;
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

        public bool $__verbs_initialized = false;

        public string $name = 'Demo';
    });

    expect($result)->toBe('{"__verbs_initialized":false,"name":"Demo"}');
});

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
