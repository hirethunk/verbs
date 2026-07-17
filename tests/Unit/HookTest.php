<?php

use Thunk\Verbs\Lifecycle\Hook;
use Thunk\Verbs\Lifecycle\Phase;

function makeHook(): Hook
{
    return new Hook(callback: fn () => null);
}

it('forcePhases overrides a phase that was explicitly skipped', function () {
    $hook = makeHook()->skipPhases(Phase::Replay)->forcePhases(Phase::Replay);

    expect($hook->runsInPhase(Phase::Replay))->toBeTrue();
});

it('defaultPhases does not override a phase that was explicitly skipped', function () {
    $hook = makeHook()->skipPhases(Phase::Replay)->defaultPhases(Phase::Replay);

    expect($hook->runsInPhase(Phase::Replay))->toBeFalse();
});

it('defaultPhases does not override a phase that was already forced', function () {
    $hook = makeHook()->forcePhases(Phase::Handle)->defaultPhases(Phase::Handle);

    expect($hook->runsInPhase(Phase::Handle))->toBeTrue();
});

it('defaultPhases fills in a phase that was never set', function () {
    $hook = makeHook()->defaultPhases(Phase::Handle);

    expect($hook->runsInPhase(Phase::Handle))->toBeTrue();
});

it('forcePhases overrides regardless of the order it is applied', function () {
    $forced_first = makeHook()->forcePhases(Phase::Replay)->skipPhases(Phase::Replay);
    $skipped_first = makeHook()->skipPhases(Phase::Replay)->forcePhases(Phase::Replay);

    expect($forced_first->runsInPhase(Phase::Replay))->toBeFalse()
        ->and($skipped_first->runsInPhase(Phase::Replay))->toBeTrue();
});
