<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Support\Serializer;

/**
 * When serialize_event_id is false (default), the Serializer excludes id from stored data.
 * VerbEvent::event() injects the row id so Events requiring id (e.g. SerializedByVerbs) deserialize correctly.
 */
it('deserializes event correctly when stored data lacks id by injecting row id', function () {
    $rowId = snowflake_id();
    $eventType = VerbEventDeserializesWithoutIdInDataTestEvent::class;

    VerbEvent::insert([
        'id' => $rowId,
        'type' => $eventType,
        'data' => json_encode([]), // Simulates serialized payload without id (default)
        'metadata' => '{}',
        'created_at' => now(),
    ]);

    $verbEvent = VerbEvent::find($rowId);
    expect($verbEvent)->not->toBeNull();

    $event = $verbEvent->event();

    expect($event)
        ->toBeInstanceOf(Event::class)
        ->and($event->id)->toBe($rowId);
});

it('overwrites id in data with row id so row remains source of truth', function () {
    $rowId = snowflake_id();
    $staleIdInData = snowflake_id() + 1;
    $eventType = VerbEventDeserializesWithoutIdInDataTestEvent::class;

    VerbEvent::insert([
        'id' => $rowId,
        'type' => $eventType,
        'data' => json_encode(['id' => $staleIdInData]),
        'metadata' => '{}',
        'created_at' => now(),
    ]);

    $verbEvent = VerbEvent::find($rowId);
    $event = $verbEvent->event();

    expect($event->id)->toBe($rowId);
});

it('excludes event id from serialization when serialize_event_id is false', function () {
    config(['verbs.serialize_event_id' => false]);

    $event = new VerbEventDeserializesWithoutIdInDataTestEvent;
    $event->id = snowflake_id();

    $serialized = app(Serializer::class)->serialize($event);

    expect($serialized)->not->toContain((string) $event->id);
});

it('includes event id in serialization when serialize_event_id is true', function () {
    config(['verbs.serialize_event_id' => true]);

    $event = new VerbEventDeserializesWithoutIdInDataTestEvent;
    $event->id = $id = snowflake_id();

    $serialized = app(Serializer::class)->serialize($event);

    expect($serialized)->toContain((string) $id);
});

class VerbEventDeserializesWithoutIdInDataTestEvent extends Event {}
