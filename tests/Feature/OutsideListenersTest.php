<?php

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Attributes\Hooks\Listen;
use Thunk\Verbs\Attributes\Hooks\On;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\State;

beforeEach(fn () => $GLOBALS['outside_listener_log'] = []);

it('can trigger listeners outside the event object', function () {
    Verbs::fake();
    Verbs::listen(OutsideListenersTestListener::class);

    OutsideListenersTestEvent1::fire(message: 'test 1');
    OutsideListenersTestEvent2::fire(message: 'test 2');
    OutsideListenersTestEvent1::fire(message: 'test 1b');

    Verbs::commit();

    // TODO: Handle state listeners

    expect($GLOBALS['outside_listener_log'])->toBe([
        // Event 1: 'test 1' — pre-commit
        '[multi listener with explicit validate phase] with OutsideListenersTestEvent1 "test 1"',
        '[multi listener with explicit validate phase] with OutsideListenersTestEvent1 "test 1"', // second validation is run against no state
        '[multi listener with explicit apply phase] with OutsideListenersTestEvent1 "test 1"',
        '[multi listener with explicit fired phase] with OutsideListenersTestEvent1 "test 1"',

        // Event 2: 'test 2' — pre-commit
        '[single listener with explicit validate phase] with OutsideListenersTestEvent2 "test 2"',
        '[multi listener with explicit validate phase] with OutsideListenersTestEvent2 "test 2"',
        '[single listener with explicit fired phase] with OutsideListenersTestEvent2 "test 2"',
        '[multi listener with explicit fired phase] with OutsideListenersTestEvent2 "test 2"',

        // Event 3: 'test 1b' — pre-commit
        '[multi listener with explicit validate phase] with OutsideListenersTestEvent1 "test 1b"',
        '[multi listener with explicit validate phase] with OutsideListenersTestEvent1 "test 1b"', // second validation is run against no state
        '[multi listener with explicit apply phase] with OutsideListenersTestEvent1 "test 1b"',
        '[multi listener with explicit fired phase] with OutsideListenersTestEvent1 "test 1b"',

        // Event 1: 'test 1' — handle
        '[reflection single event] with OutsideListenersTestEvent1 "test 1"',
        '[reflection union event] with OutsideListenersTestEvent1 "test 1"',
        '[reflection events and states] with OutsideListenersTestEvent1 "test 1"',
        '[multi listener with implicit handle phase] with OutsideListenersTestEvent1 "test 1"',
        '[multi listener with explicit handle phase] with OutsideListenersTestEvent1 "test 1"',

        // Event 2: 'test 2' — handle
        '[reflection union event] with OutsideListenersTestEvent2 "test 2"',
        '[reflection events and states] with OutsideListenersTestEvent2 "test 2"',
        '[single listener with implicit handle phase] with OutsideListenersTestEvent2 "test 2"',
        '[multi listener with implicit handle phase] with OutsideListenersTestEvent2 "test 2"',
        '[single listener with explicit handle phase] with OutsideListenersTestEvent2 "test 2"',
        '[multi listener with explicit handle phase] with OutsideListenersTestEvent2 "test 2"',

        // Event 3: 'test 1b' — handle
        '[reflection single event] with OutsideListenersTestEvent1 "test 1b"',
        '[reflection union event] with OutsideListenersTestEvent1 "test 1b"',
        '[reflection events and states] with OutsideListenersTestEvent1 "test 1b"',
        '[multi listener with implicit handle phase] with OutsideListenersTestEvent1 "test 1b"',
        '[multi listener with explicit handle phase] with OutsideListenersTestEvent1 "test 1b"',
    ]);
});

class OutsideListenersTestState extends State
{
    public array $messages = [];
}

#[AppliesToState(OutsideListenersTestState::class, 'state_id')]
class OutsideListenersTestEvent1 extends Event
{
    public function __construct(public string $message, public ?int $state_id = null)
    {
    }
}

class OutsideListenersTestEvent2 extends Event
{
    public function __construct(public string $message)
    {
    }
}

class OutsideListenersTestListener
{
    public function reflectionSingleEvent(OutsideListenersTestEvent1 $event)
    {
        $this->log($event);
    }

    public function reflectionUnionEvent(OutsideListenersTestEvent1|OutsideListenersTestEvent2 $event)
    {
        $this->log($event);
    }

    public function reflectionEventsAndStates(
        OutsideListenersTestEvent1|OutsideListenersTestEvent2|OutsideListenersTestState $trigger
    ) {
        $this->log($trigger);
    }

    #[Listen(OutsideListenersTestEvent2::class)]
    public function singleListenerWithImplicitHandlePhase($e)
    {
        $this->log($e);
    }

    #[Listen(OutsideListenersTestEvent1::class)]
    #[Listen(OutsideListenersTestEvent2::class)]
    public function multiListenerWithImplicitHandlePhase($e)
    {
        $this->log($e);
    }

    // Authorize Phase
    // ----------------------------------------------------------------------

    #[Listen(OutsideListenersTestEvent2::class)]
    #[On(Phase::Authorize)]
    public function singleListenerWithExplicitAuthorizePhase($e)
    {
        $this->log($e);
    }

    #[Listen(OutsideListenersTestEvent1::class)]
    #[Listen(OutsideListenersTestEvent2::class)]
    #[On(Phase::Authorize)]
    public function multiListenerWithExplicitAuthorizePhase($e)
    {
        $this->log($e);
    }

    // Validate Phase
    // ----------------------------------------------------------------------

    #[Listen(OutsideListenersTestEvent2::class)]
    #[On(Phase::Validate)]
    public function singleListenerWithExplicitValidatePhase($e)
    {
        $this->log($e);
    }

    #[Listen(OutsideListenersTestEvent1::class)]
    #[Listen(OutsideListenersTestEvent2::class)]
    #[On(Phase::Validate)]
    public function multiListenerWithExplicitValidatePhase($e)
    {
        $this->log($e);
    }

    // Apply Phase
    // ----------------------------------------------------------------------

    #[Listen(OutsideListenersTestEvent2::class)]
    #[On(Phase::Apply)]
    public function singleListenerWithExplicitApplyPhase($e)
    {
        $this->log($e);
    }

    #[Listen(OutsideListenersTestEvent1::class)]
    #[Listen(OutsideListenersTestEvent2::class)]
    #[On(Phase::Apply)]
    public function multiListenerWithExplicitApplyPhase($e)
    {
        $this->log($e);
    }

    // Fired Phase
    // ----------------------------------------------------------------------

    #[Listen(OutsideListenersTestEvent2::class)]
    #[On(Phase::Fired)]
    public function singleListenerWithExplicitFiredPhase($e)
    {
        $this->log($e);
    }

    #[Listen(OutsideListenersTestEvent1::class)]
    #[Listen(OutsideListenersTestEvent2::class)]
    #[On(Phase::Fired)]
    public function multiListenerWithExplicitFiredPhase($e)
    {
        $this->log($e);
    }

    // Handle Phase
    // ----------------------------------------------------------------------

    #[Listen(OutsideListenersTestEvent2::class)]
    #[On(Phase::Handle)]
    public function singleListenerWithExplicitHandlePhase($e)
    {
        $this->log($e);
    }

    #[Listen(OutsideListenersTestEvent1::class)]
    #[Listen(OutsideListenersTestEvent2::class)]
    #[On(Phase::Handle)]
    public function multiListenerWithExplicitHandlePhase($e)
    {
        $this->log($e);
    }

    // Replay Phase
    // ----------------------------------------------------------------------

    #[Listen(OutsideListenersTestEvent2::class)]
    #[On(Phase::Replay)]
    public function singleListenerWithExplicitReplayPhase($e)
    {
        $this->log($e);
    }

    #[Listen(OutsideListenersTestEvent1::class)]
    #[Listen(OutsideListenersTestEvent2::class)]
    #[On(Phase::Replay)]
    public function multiListenerWithExplicitReplayPhase($e)
    {
        $this->log($e);
    }

    protected function log($target): void
    {
        $caller = str(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'])->snake(' ');
        $target_class = class_basename($target);
        $message = property_exists($target, 'message') ? "\"{$target->message}\"" : '';

        $GLOBALS['outside_listener_log'][] = "[{$caller}] with {$target_class} $message";
    }
}
