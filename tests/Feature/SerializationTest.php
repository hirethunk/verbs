<?php

use Carbon\CarbonInterface;
use Glhd\Bits\Snowflake;
use Illuminate\Support\Str;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Thunk\Verbs\Event;
use Thunk\Verbs\SerializedByVerbs;
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
            dto: new DTO()
        );
    })->not->toThrow(TypeError::class);
});

it('honors configured context', function () {
    config()->set('verbs.serializer_context', [
        PropertyNormalizer::NORMALIZE_VISIBILITY => PropertyNormalizer::NORMALIZE_PUBLIC,
    ]);

    $target = new class()
    {
        public $is_public = 'public';

        protected $is_protected = 'protected';

        private $is_private = 'private';
    };

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
});

class EventWithConstructorPromotion extends Event
{
    public function __construct(
        public Snowflake $snowflake,
        public CarbonInterface $timestamp,
        public string $string,
    ) {
    }
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
