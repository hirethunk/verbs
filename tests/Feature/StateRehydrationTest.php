<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Thunk\Verbs\Examples\Bank\Events\AccountOpened;
use Thunk\Verbs\Examples\Bank\Events\MoneyDeposited;
use Thunk\Verbs\Examples\Bank\States\AccountState;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbSnapshot;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutExceptionHandling();

    Route::get('open', function () {
        $e = AccountOpened::fire(account_id: 1, user_id: 1, initial_deposit_in_cents: 100);

        Verbs::commit();

        return $e->state(AccountState::class)->balance_in_cents;
    });

    Route::get('deposit', function () {
        $e = MoneyDeposited::fire(account_id: 1, cents: 100);

        Verbs::commit();

        return $e->state(AccountState::class)->balance_in_cents;
    });
});

it('supports rehydrating a state from snapshots', function () {
    $this->get('open')->assertSuccessful()->assertSee(100);

    expect(VerbSnapshot::query()->count())->toBe(1);
    VerbEvent::truncate();

    $this->get('deposit')->assertSuccessful()->assertSee(200);
});

it('supports rehydrating a state from events', function () {
    $this->get('open')->assertSuccessful()->assertSee(0);

    expect(VerbEvent::query()->count())->toBe(1);
    VerbSnapshot::truncate();

    $this->get('deposit')->assertSuccessful()->assertSee(100);
});

it('supports rehydrating a state from a combination of snapshots and events', function () {
    $this->get('open')->assertSuccessful()->assertSee(0);

    expect(VerbSnapshot::query()->count())->toBe(1);
    VerbEvent::truncate();

    $snapshot = VerbSnapshot::first();

    $this->get('deposit')->assertSuccessful()->assertSee(100);

    expect(VerbEvent::query()->count())->toBe(1);
    $snapshot->save();

    $this->get('deposit')->assertSuccessful()->assertSee(200);
});
