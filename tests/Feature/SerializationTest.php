<?php

use Carbon\CarbonInterface;
use Glhd\Bits\Snowflake;
use Illuminate\Support\Str;
use Thunk\Verbs\Event;

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
