<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Attributes\Hooks\UniqueBy;
use Thunk\Verbs\Commands\ReplayCommand;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;

beforeEach(function () {
    $GLOBALS['handle_count'] = 0;
});

it('prevents duplicate writes by uniqueBy', function () {
    $state1_id = Id::make();
    $state2_id = Id::make();

    // State 1
    LatestHandleTestEvent::fire(state_id: $state1_id);
    LatestHandleTestEvent::fire(state_id: $state1_id);

    // State 2
    LatestHandleTestEvent::fire(state_id: $state2_id);
    LatestHandleTestEvent::fire(state_id: $state2_id);

    Verbs::commit();

    expect($GLOBALS['handle_count'])->toBe(2);

    $GLOBALS['handle_count'] = 0;

    $this->artisan(ReplayCommand::class);

    expect($GLOBALS['handle_count'])->toBe(2);
});

it('prevents duplicate writes automatically using the StateId attribute', function () {
    $state1_id = Id::make();
    $state2_id = Id::make();

    // State 1
    LatestHandleTestEvent::fire(state_id: $state1_id);
    AnotherLatestHandleTestEvent::fire(state_id: $state1_id);
    AnotherLatestHandleTestEvent::fire(state_id: $state1_id);

    // State 2
    LatestHandleTestEvent::fire(state_id: $state2_id);
    AnotherLatestHandleTestEvent::fire(state_id: $state2_id);

    Verbs::commit();

    expect($GLOBALS['handle_count'])->toBe(2);

    $GLOBALS['handle_count'] = 0;

    $this->artisan(ReplayCommand::class);

    expect($GLOBALS['handle_count'])->toBe(2);
});

it('prevents duplicate writes automatically using a specific name', function () {
    $state1_id = Id::make();
    $state2_id = Id::make();

    NamedHandleTestEvent::fire(state_id: $state2_id); // 5
    AnotherNamedHandleTestEvent::fire(state_id: $state2_id); // 7

    Verbs::commit();

    expect($GLOBALS['handle_count'])->toBe(1);

    $GLOBALS['handle_count'] = 0;

    $this->artisan(ReplayCommand::class);

    expect($GLOBALS['handle_count'])->toBe(1);
});

it('can receive handle data when replay_only is set', function () {
    $this->assertTrue(CommitOnlyTestEvent::commit());
    $this->assertTrue(CommitOnlyTestEvent::commit());

    expect($GLOBALS['handle_count'])->toBe(2);

    $GLOBALS['handle_count'] = 0;

    $this->artisan(ReplayCommand::class);

    expect($GLOBALS['handle_count'])->toBe(1);
});

it('only runs callbacks once', function () {
    NamedHandleTestEvent::fire();

    Verbs::whenUnique(null, function () {
        $GLOBALS['handle_count']++;
    });

    Verbs::whenUnique(null, function () {
        $GLOBALS['handle_count']++;
    });

    Verbs::whenUnique(null, function () {
        $GLOBALS['handle_count']++;
    }, 'another');

    Verbs::whenUnique(null, function () {
        $GLOBALS['handle_count']++;
    }, 'another');

    $state = LatestHandleTestState::load(snowflake_id());
    $state2 = LatestHandleTestState::load(snowflake_id());

    Verbs::whenUnique($state, function () {
        $GLOBALS['handle_count']++;
    }, 'another');

    Verbs::whenUnique($state, function () {
        $GLOBALS['handle_count']++;
    }, 'another');

    Verbs::whenUnique([$state, $state2], function () {
        $GLOBALS['handle_count']++;
    }, 'another');

    Verbs::commit();

    expect($GLOBALS['handle_count'])->toBe(5);
});

class LatestHandleTestEvent extends Event
{
    public function __construct(
        #[StateId(LatestHandleTestState::class)] public ?int $state_id = null,
    ) {}

    #[UniqueBy('state_id')]
    public function handle(): void
    {
        $GLOBALS['handle_count']++;
    }
}

class AnotherLatestHandleTestEvent extends Event
{
    public function __construct(
        #[StateId(LatestHandleTestState::class)] public ?int $state_id = null,
    ) {}

    #[UniqueBy('state_id')]
    public function handle(): void
    {
        $GLOBALS['handle_count']++;
    }
}

class NamedHandleTestEvent extends Event
{
    public function __construct(
    ) {}

    #[UniqueBy(null, name: 'named')]
    public function handle(): void
    {
        $GLOBALS['handle_count']++;
    }
}

class AnotherNamedHandleTestEvent extends Event
{
    public function __construct(
    ) {}

    #[UniqueBy(null, name: 'named')]
    public function handle(): void
    {
        $GLOBALS['handle_count']++;
    }
}

class CommitOnlyTestEvent extends Event
{
    #[UniqueBy(null, replay_only: true)]
    public function handle(): bool
    {
        $GLOBALS['handle_count']++;

        return true;
    }
}

class LatestHandleTestState extends State {}
