<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Queue;
use Thunk\Verbs\Support\PendingEvent;

it('returns a pending event when you call Event::make', function () {
    $pending = UserRegistered::make();

    expect($pending)->toBeInstanceOf(PendingEvent::class)
        ->and($pending->event)->toBeString(UserRegistered::class);
	
	$pending->hydrate([]);
	
	expect($pending->event)->toBeInstanceOf(UserRegistered::class);
});

it('calls fire on the broker when you call Event::fire', function () {
    $user_registered = UserRegistered::fire();

    $event_queue = app(Queue::class)->event_queue;

    expect($event_queue)->toHaveCount(1)
        ->and($event_queue[0])->toBe($user_registered);
});

it('immediately commits, and returns the results of handle when you call Event::fireNow', function () {
    $result = UserRegistered::fireNow();

    expect($result)->toBeInstanceOf(stdClass::class)
        ->and($result->name)->toBe('Chris')
        ->and(app(Queue::class)->event_queue)->toHaveCount(0);
});

class UserRegistered extends Event
{
    public function handle()
    {
        return (object) ['name' => 'Chris'];
    }
}
