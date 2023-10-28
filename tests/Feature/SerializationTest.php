<?php

use Thunk\Verbs\Event;

it('supports serialization', function () {
    dd(
        TestEvent::make(['number' => 33, 'name' => 'Bob', 34])
            ->hydrate(['number' => 97])
    );
});

class TestEvent extends Event
{
    public function __construct(
        public string $name,
        public int $number,
    ) {
    }
}
