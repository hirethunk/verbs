<?php

use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Thunk\Verbs\Support\Normalization\BitsNormalizer;
use Thunk\Verbs\Support\Normalization\CarbonNormalizer;
use Thunk\Verbs\Support\Normalization\CollectionNormalizer;
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
        StateNormalizer::class,
        BitsNormalizer::class,
        CarbonNormalizer::class,
        DateTimeNormalizer::class,
        BackedEnumNormalizer::class,
        PropertyNormalizer::class,
    ],
];
