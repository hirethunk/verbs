<?php

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Thunk\Verbs\Attributes\Hooks\On;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\Phase;

it('returns the result of the event handle method if no other hooks are registered for the event', function () {

    expect(CommitResultResolverTestEventWithoutReturn::commit())
        ->toBe(null);

    expect(CommitResultResolverTestEventWithReturn::commit())
        ->toBe(CommitResultResolverTestEventWithReturn::HANDLE_RESULT);
});

it('returns a collection of the results of all event handlers if other hooks are registered for the event', function () {
    Verbs::listen(new CommitResultResolverTestListenerWithReturn('foo'));
    Verbs::listen(CommitResultResolverTestListenerWithoutReturn::class);
    Verbs::listen(new CommitResultResolverTestListenerWithReturn($expectedCollection = collect(['bar', 'baz'])));
    Verbs::listen(new CommitResultResolverTestListenerWithReturn(null));
    Verbs::listen(new CommitResultResolverTestListenerWithReturn($expectedCarbon = new Carbon('2025-04-01T00:00:00.123456+02:00')));

    $result = CommitResultResolverTestEventWithoutReturn::commit();

    expect($result)->toBeInstanceOf(Collection::class);

    expect($result->all())
        ->toBe([
            null, // From our event without return
            'foo', // From our listener with `foo` return
            null, // From our listener without return
            $expectedCollection, // From our listener with `Collection` return
            null, // From our listener with `null` return
            $expectedCarbon, // From our listener with `Carbon` return
        ]);
});

it('can deterministically resolve commit return value using a global resolver callback', function () {
    Verbs::resolveHandleReturnUsing(
        fn (Collection $results, Event $event): Collection => $results->take(1)
    );

    Verbs::listen(new CommitResultResolverTestListenerWithoutReturn);
    Verbs::listen(new CommitResultResolverTestListenerWithReturn('foo'));

    $result = CommitResultResolverTestEventWithReturn::commit();

    expect($result)->toBe(CommitResultResolverTestEventWithReturn::HANDLE_RESULT);
});

class CommitResultResolverTestEventWithoutReturn extends Event
{
    public function handle(): void {}
}

class CommitResultResolverTestEventWithReturn extends Event
{
    public const HANDLE_RESULT = 'event-handle-return';

    public function handle(): mixed
    {
        return self::HANDLE_RESULT;
    }
}

class CommitResultResolverTestListenerWithoutReturn
{
    #[On(Phase::Handle)]
    public function handle(Event $event): void {}
}

class CommitResultResolverTestListenerWithReturn
{
    public function __construct(public mixed $return) {}

    #[On(Phase::Handle)]
    public function handle(Event $event): mixed
    {
        return $this->return;
    }
}
