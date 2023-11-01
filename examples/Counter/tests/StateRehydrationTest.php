<?php

// it('supports rehydrating a state from snapshots', function () {
//     $this->get('open')->assertSee(0);

//     expect(VerbSnapshot::query()->count())->toBe(1);
//     VerbEvent::truncate();

//     $this->get('deposit')->assertSee(100);
// });

it('supports rehydrating a state from events', function () {
    $this->artisan('count:increment')->expectsOutput('The count is 1.');
    $this->artisan('count:increment')->expectsOutput('The count is 2.');
    $this->artisan('count:increment')->expectsOutput('The count is 3.');


    // expect(VerbEvent::query()->count())->toBe(1);
    // // VerbSnapshot::truncate();
    // dump('request mid');

    // $this->get('deposit')->assertSee(100);
    // dump('request 2');

});

// it('supports rehydrating a state from a combination of snapshots and events', function () {
//     $this->get('open')->assertSee(0);

//     expect(VerbSnapshot::query()->count())->toBe(1);
//     VerbEvent::truncate();

//     $snapshot = VerbSnapshot::first();

//     $this->get('deposit')->assertSee(100);

//     expect(VerbEvent::query()->count())->toBe(1);
//     $snapshot->save();
    
//     $this->get('deposit')->assertSee(200);
// });
