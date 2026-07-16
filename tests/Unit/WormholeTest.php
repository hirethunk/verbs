<?php

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Support\Wormhole;

test('a callback can be run for a past timestamp', function () {
    $now = now();
    $event = new class extends Event {};
    app(MetadataManager::class)->setEphemeral($event, 'created_at', Date::parse('2023-01-02 00:00:00'));
    app(Wormhole::class)->warp($event, function () use ($now) {
        expect(Carbon::now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(CarbonImmutable::now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(app(Wormhole::class)->realNow()->format('Y-m-d'))->toBe($now->format('Y-m-d'))
            ->and(Verbs::realNow()->format('Y-m-d'))->toBe($now->format('Y-m-d'));
    });
});

test('a callback can be run for a past timestamp with "test now" set', function () {
    Date::setTestNow('2023-06-02 00:00:00');
    $event = new class extends Event {};
    app(MetadataManager::class)->setEphemeral($event, 'created_at', Date::parse('2023-01-02 00:00:00'));
    app(Wormhole::class)->warp($event, function () {
        expect(Carbon::now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(CarbonImmutable::now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(app(Wormhole::class)->realNow()->format('Y-m-d'))->toBe('2023-06-02')
            ->and(Verbs::realNow()->format('Y-m-d'))->toBe('2023-06-02');
    });
});

test('nested warps preserve the outer realNow and restore it afterward', function () {
    Date::setTestNow('2023-06-02 00:00:00');

    $outer = new class extends Event {};
    $inner = new class extends Event {};

    app(MetadataManager::class)->setEphemeral($outer, 'created_at', Date::parse('2023-01-02 00:00:00'));
    app(MetadataManager::class)->setEphemeral($inner, 'created_at', Date::parse('2022-05-05 00:00:00'));

    app(Wormhole::class)->warp($outer, function () use ($inner) {
        app(Wormhole::class)->warp($inner, function () {
            // The inner warp must not mistake the outer warp's time for the "real" now.
            expect(now()->format('Y-m-d'))->toBe('2022-05-05')
                ->and(app(Wormhole::class)->realNow()->format('Y-m-d'))->toBe('2023-06-02');
        });

        // Back in the outer warp: both the warped now and the real now are intact.
        expect(now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(app(Wormhole::class)->realNow()->format('Y-m-d'))->toBe('2023-06-02');
    });
});
