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
