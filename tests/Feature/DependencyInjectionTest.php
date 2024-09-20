<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;

it('can injects states as expected', function () {
    Verbs::fake();
    $event = DependencyInjectionTestEvent::fire();
    Verbs::commit();
    expect($event->expectations)->toEqual([]);
});

it('can inject states of same type into event handle methods by alias', function () {
    Verbs::fake();
    DependencyInjectionTestMultiHandleEvent::commit();
});

// TODO: How do we handle nullable state_ids

class DependencyInjectionTestEvent extends Event
{
    public array $expectations = [
        'test1_validate',
        'test2_validate',
        'test1_apply',
        'test2_apply',
        'test1_handle',
        'test2_handle',
    ];

    public function __construct(
        #[StateId(DependencyInjectionTestState::class)] public ?int $test1_id,
        #[StateId(DependencyInjectionTestSecondState::class)] public ?int $test2_id,
    ) {}

    public function validate1(DependencyInjectionTestState $state)
    {
        unset($this->expectations[array_search('test1_validate', $this->expectations)]);
    }

    public function validate2(DependencyInjectionTestSecondState $state)
    {
        unset($this->expectations[array_search('test2_validate', $this->expectations)]);
    }

    public function apply1(DependencyInjectionTestState $state)
    {
        unset($this->expectations[array_search('test1_apply', $this->expectations)]);
    }

    public function apply2(DependencyInjectionTestSecondState $state)
    {
        unset($this->expectations[array_search('test2_apply', $this->expectations)]);
    }

    public function handle(DependencyInjectionTestState $test1, DependencyInjectionTestSecondState $test2)
    {
        unset($this->expectations[array_search('test1_handle', $this->expectations)]);
        unset($this->expectations[array_search('test2_handle', $this->expectations)]);
    }
}

class DependencyInjectionTestMultiHandleEvent extends Event
{
    public function __construct(
        #[StateId(DependencyInjectionTestState::class)] public ?int $test1_id,
        #[StateId(DependencyInjectionTestState::class)] public ?int $test2_id,
    ) {}

    public function apply(DependencyInjectionTestState $test1, DependencyInjectionTestState $test2)
    {
        $test1->name = 'test1';
        $test2->name = 'test2';
    }

    public function handle(DependencyInjectionTestState $test1, DependencyInjectionTestState $test2)
    {
        expect($test1->name)->toBe('test1')
            ->and($test2->name)->toBe('test2');
    }
}

class DependencyInjectionTestState extends State
{
    public ?string $name;
}

class DependencyInjectionTestSecondState extends State
{
    public ?string $name;
}
