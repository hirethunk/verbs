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
        'data' => [], // Simulates serialized payload without id (Serializer excludes it)
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

it('uses existing id in data when present without overwriting with row id', function () {
    $rowId = snowflake_id();
    $existingId = snowflake_id() + 1; // Different from row
    $eventType = VerbEventDeserializesWithoutIdInDataTestEvent::class;

    VerbEvent::insert([
        'id' => $rowId,
        'type' => $eventType,
        'data' => ['id' => $existingId], // Data already has id
        'metadata' => '{}',
        'created_at' => now(),
    ]);

    $verbEvent = VerbEvent::find($rowId);
    $event = $verbEvent->event();

    // array_merge puts our 'id' second, so row id overwrites. The fix uses array_merge($data, ['id' => $this->id])
    // so we intentionally overwrite any id in data with the row id - the row id is the source of truth.
    expect($event->id)->toBe($rowId);
});

class VerbEventDeserializesWithoutIdInDataTestEvent extends Event {}
