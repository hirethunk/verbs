<?php

use Glhd\Bits\Snowflake;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;
use Thunk\Verbs\Facades\Id;

it('normalizes event ids to comparable scalars', function () {
    expect(Id::normalizeEventId(null))->toBeNull()
        ->and(Id::normalizeEventId(1234))->toBe(1234)
        ->and(Id::normalizeEventId('1234'))->toBe(1234)
        ->and(Id::normalizeEventId('01ARZ3NDEKTSV4RRFFQ69G5FAV'))->toBe('01ARZ3NDEKTSV4RRFFQ69G5FAV');
});

it('normalizes id objects to their scalar event ids', function () {
    $snowflake = Snowflake::fromId(snowflake_id());
    $ulid = new Ulid;
    $uuid = Str::uuid();

    expect(Id::normalizeEventId($snowflake))->toBe($snowflake->id())
        ->and(Id::normalizeEventId($ulid))->toBe((string) $ulid)
        ->and(Id::normalizeEventId($uuid))->toBe($uuid->toString());
});

it('keeps an all-digit ULID as a string instead of overflowing the int cast', function () {
    $all_digit_ulid = '01234567890123456789012345';

    // (int) on a 26-digit string saturates at PHP_INT_MAX, which would then
    // compare *above* every normal ULID—corrupting before/after ordering.
    expect(Id::normalizeEventId($all_digit_ulid))->toBe($all_digit_ulid)
        ->and(Id::normalizeEventId($all_digit_ulid) < Id::normalizeEventId('01ARZ3NDEKTSV4RRFFQ69G5FAV'))->toBeTrue();
});

it('normalizes event ids so they compare chronologically', function () {
    $earlier_snowflake = snowflake_id();
    $later_snowflake = snowflake_id();

    // Drivers may hand bigints back as strings with differing digit counts,
    // where lexicographic comparison would be wrong ("998" > "1010").
    expect(Id::normalizeEventId((string) $later_snowflake) > Id::normalizeEventId((string) $earlier_snowflake))->toBeTrue();

    $earlier_ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAA';
    $later_ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAB';

    expect(Id::normalizeEventId($later_ulid) > Id::normalizeEventId($earlier_ulid))->toBeTrue();
});
