<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbSnapshot;

uses(RefreshDatabase::class);

it('supports rehydrating a state from snapshots', function () {
    $this->artisan('count:increment')->expectsOutput('1');

    expect(VerbSnapshot::query()->count())->toBe(1);
    VerbEvent::truncate();

    $this->artisan('count:increment')->expectsOutput('2');
});

it('supports rehydrating a state from events', function () {
    $this->artisan('count:increment')->expectsOutput('1');

    expect(VerbEvent::query()->count())->toBe(1);
    VerbSnapshot::truncate();

    $this->artisan('count:increment')->expectsOutput('2');
});

it('supports rehydrating a state from a combination of snapshots and events', function () {
    $this->artisan('count:increment')->expectsOutput('1');

    expect(VerbSnapshot::query()->count())->toBe(1);
    VerbEvent::truncate();

    $snapshot = VerbSnapshot::first();

    $this->artisan('count:increment')->expectsOutput('2');

    expect(VerbEvent::query()->count())->toBe(1);
    $snapshot->save();

    $this->artisan('count:increment')->expectsOutput('3');
});
