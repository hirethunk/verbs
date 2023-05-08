<?php

use Illuminate\Support\Facades\Date;
use Thunk\Verbs\Facades\Snowflake;
use Thunk\Verbs\Snowflakes\Factory;
use Thunk\Verbs\Snowflakes\SequenceResolver;

it('generates unique ids', function () {
    $exists = [];
    $iterations = 10_000;
    
    for ($i = 0; $i < $iterations; $i++) {
        $exists[(string) Snowflake::make()] = true;
    }
    
    expect($exists)->toHaveCount($iterations);
});

it('generates snowflakes in the expected format', function() {
    $snowflake = Snowflake::make();
    
    expect(strlen((string) $snowflake))
        ->toBeGreaterThan(0)
        ->toBeLessThan(20);
});

it('generates snowflakes with the correct datacenter and worker IDs', function () {
    $factory1 = new Factory(now(), random_int(0, 7), random_int(0, 7));
    $factory2 = new Factory(now(), random_int(8, 15), random_int(8, 15));
    
    $snowflake1 = $factory1->make();
    $snowflake2 = $factory2->make();
    
    expect($snowflake1->datacenter_id)->toBe($factory1->datacenter_id);
    expect($snowflake2->datacenter_id)->toBe($factory2->datacenter_id);

    expect($snowflake1->worker_id)->toBe($factory1->worker_id);
    expect($snowflake2->worker_id)->toBe($factory2->worker_id);
});

it('can parse an existing snowflake', function() {
    $snowflake = Snowflake::fromId(1537200202186752);
    
    expect($snowflake->datacenter_id)->toBe(0);
    expect($snowflake->worker_id)->toBe(0);
    expect($snowflake->sequence)->toBe(0);
});

it('generates predictable snowflakes', function() {
    Date::setTestNow(now());
    
    $sequence = 0;
    
    $factory = new Factory(now(), 1, 15, 3, new class($sequence) extends SequenceResolver {
        public function __construct(public int &$sequence)
        {
        }
        
        public function next(int $timestamp): int
        {
            return $this->sequence++;
        }
    });

    $snowflake_at_epoch1 = $factory->make();
    expect($snowflake_at_epoch1->id())->toBe(0b0000000000000000000000000000000000000000000000101111000000000000)
        ->and($snowflake_at_epoch1->timestamp)->toBe(0)
        ->and($snowflake_at_epoch1->datacenter_id)->toBe(1)
        ->and($snowflake_at_epoch1->worker_id)->toBe(15)
        ->and($snowflake_at_epoch1->sequence)->toBe(0);

    $snowflake_at_epoch2 = $factory->make();
    expect($snowflake_at_epoch2->id())->toBe(0b0000000000000000000000000000000000000000000000101111000000000001)
        ->and($snowflake_at_epoch2->timestamp)->toBe(0)
        ->and($snowflake_at_epoch2->datacenter_id)->toBe(1)
        ->and($snowflake_at_epoch2->worker_id)->toBe(15)
        ->and($snowflake_at_epoch2->sequence)->toBe(1);

    Date::setTestNow(now()->addMillisecond());
    $snowflake_at_1ms = $factory->make();
    expect($snowflake_at_1ms->id())->toBe(0b0000000000000000000000000000000000000000010000101111000000000010)
        ->and($snowflake_at_1ms->timestamp)->toBe(1)
        ->and($snowflake_at_1ms->datacenter_id)->toBe(1)
        ->and($snowflake_at_1ms->worker_id)->toBe(15)
        ->and($snowflake_at_1ms->sequence)->toBe(2);

    Date::setTestNow(now()->addMillisecond());
    $snowflake_at_2ms = $factory->make();
    expect($snowflake_at_2ms->id())->toBe(0b0000000000000000000000000000000000000000100000101111000000000011)
        ->and($snowflake_at_2ms->timestamp)->toBe(2)
        ->and($snowflake_at_2ms->datacenter_id)->toBe(1)
        ->and($snowflake_at_2ms->worker_id)->toBe(15)
        ->and($snowflake_at_2ms->sequence)->toBe(3);
});

it('can generate a snowflake for a given timestamp', function() {
    Date::setTestNow(now());
    
    $factory = new Factory(now(), 31, 31, 3, new class extends SequenceResolver {
        public function next(int $timestamp): int
        {
            return 4095;
        }
    });
    
    $a = $factory->makeFromTimestampForQuery(now()->addMinutes(30));
    
    // FIXME: Should sequence be considered?
    
    expect($a->id())->toBe(0b0000000000000000000001101101110111010000000000000000000000000000)
        ->and($a->timestamp)->toBe(1_800_000)
        ->and($a->datacenter_id)->toBe(0)
        ->and($a->worker_id)->toBe(0)
        ->and($a->sequence)->toBe(0);
    
    $b = $factory->makeFromTimestampForQuery(now()->addMinutes(60));

    expect($b->id())->toBe(0b0000000000000000000011011011101110100000000000000000000000000000)
        ->and($b->timestamp)->toBe(3_600_000)
        ->and($b->datacenter_id)->toBe(0)
        ->and($b->worker_id)->toBe(0)
        ->and($b->sequence)->toBe(0);
    
    $minutes = ($b->timestamp - $a->timestamp) / 60_000;
    expect($minutes)->toBe(30);
});
