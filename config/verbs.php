<?php

use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Thunk\Verbs\Support\Normalization\BitsNormalizer;
use Thunk\Verbs\Support\Normalization\CarbonNormalizer;
use Thunk\Verbs\Support\Normalization\CollectionNormalizer;
use Thunk\Verbs\Support\Normalization\ModelNormalizer;
use Thunk\Verbs\Support\Normalization\SelfSerializingNormalizer;
use Thunk\Verbs\Support\Normalization\StateNormalizer;

return [
    /*
    |--------------------------------------------------------------------------
    | ID Type
    |--------------------------------------------------------------------------
    |
    | By default, Verbs uses 64-bit integer IDs called "Snowflakes." If you
    | would like to use ULIDs or UUIDs, you must configure it here.
    |
    | WARNING: Once you have saved events to your database, it will be hard
    |          to change your ID format. Choose wisely! (We recommend the
    |          default unless you have a good reason otherwise.)
    |
    | Options: "snowflake", "ulid", or "uuid"
    |
    */
    'id_type' => env('VERBS_ID_TYPE', 'snowflake'),

    /*
    |--------------------------------------------------------------------------
    | Normalizers
    |--------------------------------------------------------------------------
    |
    | Verbs uses the Symfony Serializer component (https://symfony.com/components/Serializer)
    | to serialize your PHP Event objects to JSON. The default normalizers
    | should handle most stock Laravel applications, but you may need to add
    | your own normalizers for certain object types.
    |
    */
    'normalizers' => [
        SelfSerializingNormalizer::class,
        CollectionNormalizer::class,
        ArrayDenormalizer::class,
        ModelNormalizer::class,
        StateNormalizer::class,
        BitsNormalizer::class,
        CarbonNormalizer::class,
        DateTimeNormalizer::class,
        BackedEnumNormalizer::class,
        PropertyNormalizer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Serializer Context
    |--------------------------------------------------------------------------
    |
    | The Symfony Serializer can be configured using "Context" (https://symfony.com/doc/current/serializer.html#serializer-context)
    | which modifies the default behavior of normalizers. You can use this to change
    | things like the default date format, or whether private and protected
    | properties should be serialized (by default, Verbs only serializes public props).
    |
    */
    'serializer_context' => [
        PropertyNormalizer::NORMALIZE_VISIBILITY => PropertyNormalizer::NORMALIZE_PUBLIC,
    ],

    /*
   |--------------------------------------------------------------------------
   | Connection Names
   |--------------------------------------------------------------------------
   |
   | By default, Verbs will use your default database connection, However, you may
   | wish to customize these connection names to better fit your application.
   |
   */
    'connections' => [
        'events' => env('VERBS_EVENTS_CONNECTION'),
        'snapshots' => env('VERBS_SNAPSHOT_CONNECTION'),
        'state_events' => env('VERBS_STATE_EVENTS_CONNECTION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | By default, Verbs prefixes all of its table names with "verb_". However, you
    | may wish to customize these table names to better fit your application.
    |
    */
    'tables' => [
        'events' => 'verb_events',
        'snapshots' => 'verb_snapshots',
        'state_events' => 'verb_state_events',
    ],

    /*
    |--------------------------------------------------------------------------
    | Wormhole
    |--------------------------------------------------------------------------
    |
    | When replaying events, Verbs will set the "now" timestamp for `Carbon`
    | and `CarbonImmutable` instances to the moment the original event was
    | stored in the database. This allows you to use the `now()` helper in your
    | event handlers easily. You can disable this feature if you'd like.
    |
    */
    'wormhole' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto-Commit
    |--------------------------------------------------------------------------
    |
    | By default, Verbs will auto-commit events to the event store for you:
    |
    |   - at the end of every request (before returning a response)
    |   - at the end of every console command
    |   - at the end of every queued job
    |
    | If you want to always manually commit, you can disable auto-commit.
    */
    'autocommit' => true,
];
