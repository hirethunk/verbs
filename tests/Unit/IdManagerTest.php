<?php

use Glhd\Bits\Snowflake;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;
use Thunk\Verbs\Facades\Id;

it('normalizes positions to comparable scalars', function () {
    expect(Id::normalizePosition(null))->toBeNull()
        ->and(Id::normalizePosition(1234))->toBe(1234)
        ->and(Id::normalizePosition('1234'))->toBe(1234)
        ->and(Id::normalizePosition('01ARZ3NDEKTSV4RRFFQ69G5FAV'))->toBe('01ARZ3NDEKTSV4RRFFQ69G5FAV');
});

it('normalizes id objects to their scalar positions', function () {
    $snowflake = Snowflake::fromId(snowflake_id());
    $ulid = new Ulid;
    $uuid = Str::uuid();

    expect(Id::normalizePosition($snowflake))->toBe($snowflake->id())
        ->and(Id::normalizePosition($ulid))->toBe((string) $ulid)
        ->and(Id::normalizePosition($uuid))->toBe($uuid->toString());
});

it('normalizes positions so they compare chronologically', function () {
    $earlier_snowflake = snowflake_id();
    $later_snowflake = snowflake_id();

    // Drivers may hand bigints back as strings with differing digit counts,
    // where lexicographic comparison would be wrong ("998" > "1010").
    expect(Id::normalizePosition((string) $later_snowflake) > Id::normalizePosition((string) $earlier_snowflake))->toBeTrue();

    $earlier_ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAA';
    $later_ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAB';

    expect(Id::normalizePosition($later_ulid) > Id::normalizePosition($earlier_ulid))->toBeTrue();
});
