<?php

use Thunk\Verbs\Attributes\Hooks\On;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\State;

beforeEach(fn () => $GLOBALS['outside_listener_log'] = []);

it('can trigger listeners outside the event object', function () {
    Verbs::fake();
    Verbs::listen(OutsideListenersTestListener::class);

    OutsideListenersTestEvent1::fire(message: 'test 1a');
    OutsideListenersTestEvent2::fire(message: 'test 2');
    OutsideListenersTestEvent1::fire(message: 'test 1b');

    Verbs::commit();

    expect($GLOBALS['outside_listener_log'])->toBe([
        // Event 1: 'test 1a' — pre-commit
        'unionListenerWithExplicitBootPhase with OutsideListenersTestEvent1 "test 1a"',
        'unionListenerWithExplicitValidatePhase with OutsideListenersTestEvent1 "test 1a"',
        'unionListenerWithExplicitApplyPhase with OutsideListenersTestEvent1 "test 1a"',
        'unionListenerWithExplicitFiredPhase with OutsideListenersTestEvent1 "test 1a"',

        // Event 2: 'test 2' — pre-commit
        'singleListenerWithExplicitBootPhase with OutsideListenersTestEvent2 "test 2"',
        'unionListenerWithExplicitBootPhase with OutsideListenersTestEvent2 "test 2"',
        'singleListenerWithExplicitValidatePhase with OutsideListenersTestEvent2 "test 2"',
        'unionListenerWithExplicitValidatePhase with OutsideListenersTestEvent2 "test 2"',
        'singleListenerWithExplicitApplyPhase with OutsideListenersTestEvent2 "test 2"',
        'unionListenerWithExplicitApplyPhase with OutsideListenersTestEvent2 "test 2"',
        'singleListenerWithExplicitFiredPhase with OutsideListenersTestEvent2 "test 2"',
        'unionListenerWithExplicitFiredPhase with OutsideListenersTestEvent2 "test 2"',

        // Event 3: 'test 1b' — pre-commit
        'unionListenerWithExplicitBootPhase with OutsideListenersTestEvent1 "test 1b"',
        'unionListenerWithExplicitValidatePhase with OutsideListenersTestEvent1 "test 1b"',
        'unionListenerWithExplicitApplyPhase with OutsideListenersTestEvent1 "test 1b"',
        'unionListenerWithExplicitFiredPhase with OutsideListenersTestEvent1 "test 1b"',

        // Event 1: 'test 1a' — handle
        'unionListenerWithExplicitHandlePhase with OutsideListenersTestEvent1 "test 1a"',

        // Event 2: 'test 2' — handle
        'singleListenerWithExplicitHandlePhase with OutsideListenersTestEvent2 "test 2"',
        'unionListenerWithExplicitHandlePhase with OutsideListenersTestEvent2 "test 2"',

        // Event 3: 'test 1b' — handle
        'unionListenerWithExplicitHandlePhase with OutsideListenersTestEvent1 "test 1b"',
    ]);
});

class OutsideListenersTestState extends State
{
    public array $messages = [];
}

class OutsideListenersTestEvent1 extends Event
{
    public function __construct(public string $message, public ?OutsideListenersTestState $state_id = null) {}
}

class OutsideListenersTestEvent2 extends Event
{
    public function __construct(public string $message) {}
}

class OutsideListenersTestListener
{
    // Boot Phase
    // ----------------------------------------------------------------------

    #[On(Phase::Boot)]
    public function singleListenerWithExplicitBootPhase(OutsideListenersTestEvent2 $e)
    {
        $this->log(__FUNCTION__, $e);
    }

    #[On(Phase::Boot)]
    public function unionListenerWithExplicitBootPhase(OutsideListenersTestEvent1|OutsideListenersTestEvent2 $e)
    {
        $this->log(__FUNCTION__, $e);
    }

    // Authorize Phase is currently not run thru dispatcher. I'm going to
    // leave that for a separate pull request later.

    // Validate Phase
    // ----------------------------------------------------------------------

    #[On(Phase::Validate)]
    public function singleListenerWithExplicitValidatePhase(OutsideListenersTestEvent2 $e)
    {
        $this->log(__FUNCTION__, $e);
    }

    #[On(Phase::Validate)]
    public function unionListenerWithExplicitValidatePhase(OutsideListenersTestEvent1|OutsideListenersTestEvent2 $e)
    {
        $this->log(__FUNCTION__, $e);
    }

    // Apply Phase
    // ----------------------------------------------------------------------

    #[On(Phase::Apply)]
    public function singleListenerWithExplicitApplyPhase(OutsideListenersTestEvent2 $e)
    {
        $this->log(__FUNCTION__, $e);
    }

    #[On(Phase::Apply)]
    public function unionListenerWithExplicitApplyPhase(OutsideListenersTestEvent1|OutsideListenersTestEvent2 $e)
    {
        $this->log(__FUNCTION__, $e);
    }

    // Fired Phase
    // ----------------------------------------------------------------------

    #[On(Phase::Fired)]
    public function singleListenerWithExplicitFiredPhase(OutsideListenersTestEvent2 $e)
    {
        $this->log(__FUNCTION__, $e);
    }

    #[On(Phase::Fired)]
    public function unionListenerWithExplicitFiredPhase(OutsideListenersTestEvent1|OutsideListenersTestEvent2 $e)
    {
        $this->log(__FUNCTION__, $e);
    }

    // Handle Phase
    // ----------------------------------------------------------------------

    #[On(Phase::Handle)]
    public function singleListenerWithExplicitHandlePhase(OutsideListenersTestEvent2 $e)
    {
        $this->log(__FUNCTION__, $e);
    }

    #[On(Phase::Handle)]
    public function unionListenerWithExplicitHandlePhase(OutsideListenersTestEvent1|OutsideListenersTestEvent2 $e)
    {
        $this->log(__FUNCTION__, $e);
    }

    // Replay Phase
    // ----------------------------------------------------------------------

    #[On(Phase::Replay)]
    public function singleListenerWithExplicitReplayPhase(OutsideListenersTestEvent2 $e)
    {
        $this->log(__FUNCTION__, $e);
    }

    #[On(Phase::Replay)]
    public function unionListenerWithExplicitReplayPhase(OutsideListenersTestEvent1|OutsideListenersTestEvent2 $e)
    {
        $this->log(__FUNCTION__, $e);
    }

    protected function log(string $caller, Event $target): void
    {
        $target_class = class_basename($target);
        $message = property_exists($target, 'message') ? "\"{$target->message}\"" : '';

        $GLOBALS['outside_listener_log'][] = "{$caller} with {$target_class} $message";
    }
}
