<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;

it('can store and replay events that have no state', function () {

    $GLOBALS['stateless_test_log'] = [];

    StatelessEventOne::fire(label: 'First event');
    StatelessEventTwo::fire(label: 'Second event');
    StatelessEventOne::fire(label: 'Third event');
    StatelessEventTwo::fire(label: 'Fourth event');

    expect($GLOBALS['stateless_test_log'])->toBeEmpty();

    Verbs::commit();

    expect($GLOBALS['stateless_test_log'])->toBe([
        '[1] First event',
        '[2] Second event',
        '[1] Third event',
        '[2] Fourth event',
    ]);

    $GLOBALS['stateless_test_log'] = [];

    Verbs::replay();

    expect($GLOBALS['stateless_test_log'])->toBe([
        '[1] First event',
        '[2] Second event',
        '[1] Third event',
        '[2] Fourth event',
    ]);
});

class StatelessEventOne extends Event
{
    public function __construct(public string $label) {}

    public function handle()
    {
        $GLOBALS['stateless_test_log'][] = "[1] {$this->label}";
    }
}

class StatelessEventTwo extends Event
{
    public function __construct(public string $label) {}

    public function handle()
    {
        $GLOBALS['stateless_test_log'][] = "[2] {$this->label}";
    }
}
