<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Models\VerbEvent;

/**
 * When the Serializer stores Events, it excludes the `id` attribute (see Serializer::serializationContext).
 * Stored data therefore lacks `id`. VerbEvent::event() must inject the row id before deserializing
 * so that Events requiring `id` (e.g. SerializedByVerbs) can be reconstructed correctly.
 */
it('deserializes event correctly when stored data lacks id by injecting row id', function () {
    $rowId = snowflake_id();
    $eventType = VerbEventDeserializesWithoutIdInDataTestEvent::class;

    VerbEvent::insert([
        'id' => $rowId,
        'type' => $eventType,
        'data' => json_encode([]), // Simulates serialized payload without id (Serializer excludes it)
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

class VerbEventDeserializesWithoutIdInDataTestEvent extends Event {}
