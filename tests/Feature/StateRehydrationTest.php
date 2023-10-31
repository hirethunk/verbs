<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Thunk\Verbs\Examples\Bank\Events\AccountOpened;
use Thunk\Verbs\Examples\Bank\Events\MoneyDeposited;
use Thunk\Verbs\Examples\Bank\States\AccountState;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::get('open', function () {
        $e = AccountOpened::fire();

        dump($e->state(AccountState::class));
        Verbs::commit();
        dump('hi');
        return $e->state(AccountState::class)->balance_in_cents;
    });

    Route::get('deposit', function () {
        $e = MoneyDeposited::fire(cents: 100);

        dump($e->state(AccountState::class));
        Verbs::commit();
        return $e->state(AccountState::class)->balance_in_cents;
    });
});

// it('supports rehydrating a state from snapshots', function () {
//     $this->get('open')->assertSee(0);

//     expect(VerbSnapshot::query()->count())->toBe(1);
//     VerbEvent::truncate();

//     $this->get('deposit')->assertSee(100);
// });

it('supports rehydrating a state from events', function () {
    $this->get('open')->assertSee(0);

    expect(VerbEvent::query()->count())->toBe(1);
    // VerbSnapshot::truncate();

    $this->get('deposit')->assertSee(100);
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
